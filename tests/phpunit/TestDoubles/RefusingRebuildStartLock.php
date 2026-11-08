<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use Closure;
use ProfessionalWiki\SWS\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\SWS\Application\GraphRebuild\RebuildStartLock;
use ProfessionalWiki\SWS\Application\GraphRebuild\RebuildStartLockUnavailableException;

/**
 * Stands in for a start lock another process is holding on to, which one test cannot produce for itself.
 */
class RefusingRebuildStartLock implements RebuildStartLock {

	public function whileHeld( string $storeName, Closure $start ): RebuildRun {
		throw new RebuildStartLockUnavailableException( $storeName );
	}

}
