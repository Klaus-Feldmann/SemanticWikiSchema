<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Persistence\SubjectPageIndex;
use Wikimedia\Rdbms\IDatabase;

/**
 * Maintains `sws_subject_page` on the wiki's primary database (ADR 32).
 *
 * Writes go through the connection the surrounding operation is already using, so indexing an edit joins
 * the transaction that writes its revision: the index commits with that revision or not at all, and can
 * never hold a Subject set no revision has. The delete, undelete and import paths are hooked outside that
 * atomic section, but a web request holds the whole operation in one transaction (DBO_TRX), so a failure
 * there aborts the operation rather than leaving it committed. A CLI maintenance script runs without that
 * transaction, so there the write is best-effort and a failure leaves the index wrong until a rebuild.
 */
class DatabaseSubjectPageIndex implements SubjectPageIndex {

	public const string TABLE = 'sws_subject_page';

	public function __construct(
		private readonly IDatabase $db,
		private readonly bool $enforceSequentialIds = false,
	) {
	}

	/**
	 * @param string[] $subjectIds
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		$wanted = $subjectIds;
		sort( $wanted );
		$stored = $this->subjectsOfPage( $pageId );
		// Existing revisions already established the host invariant. Most saves change no IDs,
		// so preserve the old one-read/no-write fast path, including source-snapshot updates.
		if ( $stored === $wanted ) { return; }
		$changedSequentialIds = $this->enforceSequentialIds && array_filter( array_merge( $stored, $wanted ),
			static fn ( $id ) => preg_match( '/^E[1-9][0-9]{0,17}\z/', $id ) === 1 ) !== [];
		if ( !$changedSequentialIds ) { $this->replaceSubjects( $pageId, $wanted, $stored ); return; }
		$scope = $this->db->startAtomic( __METHOD__, IDatabase::ATOMIC_CANCELABLE );
		try {
			$this->synchronizeIdentityHosts( $pageId, $wanted );
			$this->replaceSubjects( $pageId, $wanted, $stored );
			$this->db->endAtomic( __METHOD__ );
		} catch ( \Throwable $error ) {
			$this->db->cancelAtomic( __METHOD__, $scope );
			throw $error;
		}
	}

    /** Explicit repair also checks hosting rows when the ordinary one-read fast path would skip them. */
    public function reconcileSubjectsOfPage(PageId $pageId,array $subjectIds):void {
        $scope=$this->db->startAtomic(__METHOD__,IDatabase::ATOMIC_CANCELABLE);
        try {
            $this->synchronizeIdentityHosts($pageId,$subjectIds);
            $this->setSubjectsOfPage($pageId,$subjectIds);
            $this->db->endAtomic(__METHOD__);
        } catch (\Throwable $error) { $this->db->cancelAtomic(__METHOD__,$scope);throw $error; }
    }

	private function replaceSubjects( PageId $pageId, array $wanted, array $stored ): void {

		// Replacing the rows is one step. The maintenance rebuild runs under CLI without DBO_TRX, so
		// without this the delete and the insert commit separately, leaving a window in which the page's
		// Subjects resolve to nothing. On the edit path this nests inside PageUpdater's atomic section,
		// where it is bookkeeping that issues no SQL of its own.
		$this->db->startAtomic( __METHOD__ );

		if ( $stored !== [] ) {
			$this->removeSubjectsOfPage( $pageId, $stored );
		}

		if ( $wanted !== [] ) {
			$this->insertSubjectsOfPage( $pageId, $wanted );
		}

		$this->db->endAtomic( __METHOD__ );
	}

	/**
	 * Deletes by primary key rather than by page id, which is what {@see removePage} does. Deleting by
	 * page id alone walks the `swssp_page_id` index and gap-locks the range around it, so two pages saved
	 * at once whose ids sit next to each other in that index block one another, and can deadlock. Naming
	 * both key columns turns each row into its own lookup, and Subject ids are random, so the rows a page
	 * holds are scattered rather than adjacent. MediaWiki core does the same for `page_restrictions`
	 * (T214035).
	 *
	 * @param string[] $subjectIds
	 */
	private function removeSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'swssp_subject_id' => $subjectIds, 'swssp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @param string[] $subjectIds
	 */
	private function insertSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		$this->db->newInsertQueryBuilder()
			->insertInto( self::TABLE )
			->rows( array_map(
				static fn ( string $subjectId ): array => [
					'swssp_subject_id' => $subjectId,
					'swssp_page_id' => $pageId->id,
				],
				$subjectIds
			) )
			->caller( __METHOD__ )
			->execute();
	}

	/**
	 * @return string[] Sorted here rather than by the database, so the comparison does not depend on
	 *   the column's collation.
	 */
	private function subjectsOfPage( PageId $pageId ): array {
		$stored = $this->db->newSelectQueryBuilder()
			->select( 'swssp_subject_id' )
			->from( self::TABLE )
			->where( [ 'swssp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->fetchFieldValues();

		sort( $stored );

		return $stored;
	}

	private function synchronizeIdentityHosts( PageId $pageId, array $wanted ): void {
		// The revision save owns the outer transaction. Old IDs keep their historical behavior.
		$ids = array_values( array_filter( $wanted, static fn ( $id ) => preg_match( '/^E[1-9][0-9]{0,17}\z/', $id ) === 1 ) );
		$old = $this->db->selectFieldValues( 'sws_identity_host', 'swsih_id', [ 'swsih_page'=>$pageId->id ], __METHOD__ );
		foreach ( array_diff( $old, $ids ) as $id ) {
			$this->db->delete( 'sws_identity_host', [ 'swsih_id'=>$id, 'swsih_page'=>$pageId->id ], __METHOD__ );
		}
		foreach ( $ids as $id ) {
			if ( !$this->db->selectField( 'sws_identity', 'swsi_id', [ 'swsi_id'=>$id, 'swsi_kind'=>'entity' ], __METHOD__ ) ) {
				throw new \RuntimeException( 'Entity ID was not reserved: ' . $id );
			}
			$this->db->insert( 'sws_identity_host', [ 'swsih_id'=>$id, 'swsih_page'=>$pageId->id ], __METHOD__, [ 'IGNORE' ] );
			$host = $this->db->selectField( 'sws_identity_host', 'swsih_page', [ 'swsih_id'=>$id ], __METHOD__, [ 'FOR UPDATE' ] );
			if ( (int)$host !== $pageId->id ) { throw new \ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException( new \ProfessionalWiki\SWS\Domain\Subject\SubjectId( $id ) ); }
			$this->db->update( 'sws_identity', [ 'swsi_used'=>1 ], [ 'swsi_id'=>$id ], __METHOD__ );
		}
	}

	public function removePage( PageId $pageId ): void {
		if ( $this->enforceSequentialIds ) { $this->db->delete( 'sws_identity_host', [ 'swsih_page'=>$pageId->id ], __METHOD__ ); }
		$this->db->newDeleteQueryBuilder()
			->deleteFrom( self::TABLE )
			->where( [ 'swssp_page_id' => $pageId->id ] )
			->caller( __METHOD__ )
			->execute();
	}

}
