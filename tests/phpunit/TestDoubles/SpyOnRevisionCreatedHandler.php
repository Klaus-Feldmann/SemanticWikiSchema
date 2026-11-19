<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use MediaWiki\Revision\RevisionRecord;
use AEWI\SWS\Application\PageRefreshOutcome;
use AEWI\SWS\EntryPoints\OnRevisionCreatedHandler;

class SpyOnRevisionCreatedHandler extends OnRevisionCreatedHandler {

	/** @var list<RevisionRecord> */
	public array $calls = [];

	public PageRefreshOutcome $outcome = PageRefreshOutcome::Refreshed;

	public function __construct() {
	}

	public function onRevisionCreated( RevisionRecord $revisionRecord ): PageRefreshOutcome {
		$this->calls[] = $revisionRecord;
		return $this->outcome;
	}

}
