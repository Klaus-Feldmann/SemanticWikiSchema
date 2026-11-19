<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use MediaWiki\Title\Title;
use AEWI\SWS\Application\PageDependencyRecorder;
use AEWI\SWS\Domain\Schema\SchemaReference;

class NullPageDependencyRecorder implements PageDependencyRecorder {

	public function recordDependencyOn( Title $page ): void {
	}

	public function recordDependencyOnSchema( SchemaReference $schema ): void {
	}

}
