<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use MediaWiki\Page\ProperPageIdentity;
use AEWI\SWS\Persistence\PageDeleter;
use AEWI\SWS\Persistence\PageDeletionStatus;

class PageDeleterSpy implements PageDeleter {

	/**
	 * @var string[] DB keys of the pages the action asked to delete, in order.
	 */
	public array $deletedKeys = [];

	public function deletePage( ProperPageIdentity $page, string $reason ): PageDeletionStatus {
		$this->deletedKeys[] = $page->getDBkey();

		return new PageDeletionStatus( true );
	}

}
