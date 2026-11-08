<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;

class NullPageDependencyRecorder implements PageDependencyRecorder {

	public function recordDependencyOn( Title $page ): void {
	}

	public function recordDependencyOnSchema( SchemaReference $schema ): void {
	}

}
