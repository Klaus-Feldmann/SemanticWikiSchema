<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use Closure;
use AEWI\SWS\Domain\GraphRebuild\RebuildRun;
use AEWI\SWS\Application\GraphRebuild\RebuildStartLock;
use AEWI\SWS\Application\GraphRebuild\RebuildStartLockUnavailableException;

/**
 * Stands in for a start lock another process is holding on to, which one test cannot produce for itself.
 */
class RefusingRebuildStartLock implements RebuildStartLock {

	public function whileHeld( string $storeName, Closure $start ): RebuildRun {
		throw new RebuildStartLockUnavailableException( $storeName );
	}

}
