<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use ProfessionalWiki\SWS\Application\ReferencingSubjectLookup;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use stdClass;
use Wikimedia\Rdbms\IConnectionProvider;

/**
 * Ordered by name, with generated names after the chosen ones and among themselves by Schema name: a reader sees a
 * generated name as the Subject id, which would look out of place amid the names.
 */
class DatabaseReferencingSubjectLookup implements ReferencingSubjectLookup {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
	) {
	}

	/**
	 * @return SubjectId[]
	 */
	public function getIdsOfSubjectsReferencing( SubjectId $target, int $limit ): array {
		$db = $this->connectionProvider->getReplicaDatabase();

		$result = $db->newSelectQueryBuilder()
			->select( [
				'swsr_subject_id',
				'name_is_generated' => $db->conditional( [ 'swss_display_name_key' => null ], '1', '0' ),
				'sort_name' => 'COALESCE(swss_display_name_key, swss_schema)',
			] )
			->distinct()
			->from( DatabasePublishedSubjectIndex::RELATION_TABLE )
			->join(
				DatabasePublishedSubjectIndex::SUBJECT_TABLE,
				'subject',
				[ 'subject.swss_page_id = swsr_page_id', 'subject.swss_subject_id = swsr_subject_id' ]
			)
			->where( [ 'swsr_target_id' => $target->text ] )
			->andWhere( $db->expr( 'swsr_subject_id', '!=', $target->text ) )
			->andWhere( LowestPageRows::condition( $db, 'subject' ) )
			->orderBy( [ 'name_is_generated', 'sort_name', 'swsr_subject_id' ] )
			->limit( $limit )
			->caller( __METHOD__ )
			->fetchResultSet();

		$ids = [];

		/** @var stdClass $row */
		foreach ( $result as $row ) {
			$ids[] = new SubjectId( (string)$row->swsr_subject_id );
		}

		return $ids;
	}

}
