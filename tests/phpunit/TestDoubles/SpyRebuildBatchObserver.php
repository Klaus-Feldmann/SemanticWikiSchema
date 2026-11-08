<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Application\GraphRebuild\RebuildBatchObserver;
use ProfessionalWiki\SWS\Domain\GraphRebuild\RebuildRun;

/**
 * Records the run as it stood after each batch, so a test can see how a rebuild advanced rather than
 * only where it ended up.
 */
class SpyRebuildBatchObserver implements RebuildBatchObserver {

	/**
	 * @var RebuildRun[]
	 */
	public array $pageBatches = [];

	/**
	 * @var int[] What each deletion batch removed on its own.
	 */
	public array $removedInBatch = [];

	/**
	 * @var int[]
	 */
	public array $failedPageIds = [];

	public function pageFailed( int $pageId ): void {
		$this->failedPageIds[] = $pageId;
	}

	public function afterPageBatch( RebuildRun $run ): void {
		$this->pageBatches[] = $run;
	}

	public function afterDeletionBatch( RebuildRun $run, int $removedInBatch ): void {
		$this->removedInBatch[] = $removedInBatch;
	}

}
