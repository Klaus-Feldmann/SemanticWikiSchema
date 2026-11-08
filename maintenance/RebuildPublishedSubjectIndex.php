<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Maintenance;

use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Maintenance\LoggedUpdateMaintenance;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabasePublishedSubjectIndex;
use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabasePublishedSubjectIndexRebuilder;

$basePath = getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) : __DIR__ . '/../../..';

require_once $basePath . '/maintenance/Maintenance.php';

/**
 * Running it again after update.php needs --force, since the update is logged as done.
 */
class RebuildPublishedSubjectIndex extends LoggedUpdateMaintenance {

	private const int REPORTED_PAGES = 5;

	public function __construct() {
		parent::__construct();

		$this->setBatchSize( DatabasePublishedSubjectIndexRebuilder::DEFAULT_BATCH_SIZE );
		$this->requireExtension( 'SemanticWikiSchema' );
		$this->addDescription(
			'Rebuilds the published Subject index, which the Subject lists, relation suggestions and Subject ' .
			'counts read, from the revision every page publishes. Run this after merging page histories, which ' .
			'can write a revision the index is not told about.'
		);
	}

	protected function getUpdateKey(): string {
		return 'sws-rebuild-published-subject-index';
	}

	protected function doDBUpdates(): bool {
		// update.php runs its post-update scripts even when told to apply no schema changes, so the tables can
		// be missing. Not done, so the run that creates them still fills them.
		if ( !$this->indexTablesExist() ) {
			$this->output( "The SWS published Subject index tables do not exist yet; nothing to rebuild.\n" );
			return false;
		}

		$this->output( "Rebuilding the SWS published Subject index...\n" );

		$reindexed = 0;
		$rebuild = $this->newRebuilder()->rebuild();

		foreach ( $rebuild as $reindexed ) {
			$this->output( "...$reindexed pages reindexed\n" );
			$this->waitForReplication();
		}

		$this->output( "Done. Reindexed $reindexed pages.\n" );

		$leftAsTheyWere = $rebuild->getReturn();

		if ( $leftAsTheyWere !== [] ) {
			$this->reportPagesLeftAsTheyWere( $leftAsTheyWere );
		}

		return true;
	}

	/**
	 * To stderr, which reaches whoever scheduled the run; a few page ids show what the pages have in common.
	 *
	 * @param int[] $pageIds
	 */
	private function reportPagesLeftAsTheyWere( array $pageIds ): void {
		$named = array_slice( $pageIds, 0, self::REPORTED_PAGES );
		$rest = count( $pageIds ) - count( $named );

		$this->error(
			'Did not reindex pages ' . implode( ', ', $named ) . ( $rest === 0 ? '' : " and $rest more" )
			. ': their published Subject data cannot be read. The SWS log channel says why; once they are fixed, '
			. 'run this script again with --force.'
		);
	}

	private function indexTablesExist(): bool {
		$db = $this->getDB( DB_PRIMARY );

		return $db->tableExists( DatabasePublishedSubjectIndex::SUBJECT_TABLE, __METHOD__ )
			&& $db->tableExists( DatabasePublishedSubjectIndex::RELATION_TABLE, __METHOD__ );
	}

	private function newRebuilder(): DatabasePublishedSubjectIndexRebuilder {
		return new DatabasePublishedSubjectIndexRebuilder(
			db: $this->getPrimaryDB(),
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			revisionPolicy: SWSExtension::getInstance()->getRevisionPolicy(),
			titleFormatter: $this->getServiceContainer()->getTitleFormatter(),
			dependentSubjectRules: SWSExtension::getInstance()->newUnrestrictedDependentSubjectRules(),
			logger: LoggerFactory::getInstance( 'SWS' ),
			batchSize: $this->getBatchSize(),
		);
	}

}

$maintClass = RebuildPublishedSubjectIndex::class;
require_once RUN_MAINTENANCE_IF_MAIN;
