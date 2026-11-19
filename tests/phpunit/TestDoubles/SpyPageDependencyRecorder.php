<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use AEWI\SWS\Application\PageDependencyRecorder;
use AEWI\SWS\Domain\Schema\SchemaReference;

class SpyPageDependencyRecorder implements PageDependencyRecorder {

	/**
	 * @var TitleValue[]
	 */
	public array $recordedPages = [];

	/**
	 * @var SchemaReference[]
	 */
	public array $recordedSchemas = [];

	public function recordDependencyOn( Title $page ): void {
		$this->recordedPages[] = $page->getTitleValue();
	}

	public function recordDependencyOnSchema( SchemaReference $schema ): void {
		$this->recordedSchemas[] = $schema;
	}

}
