<?php

declare( strict_types=1 );

namespace AEWI\SWS\Tests\TestDoubles;

use MediaWiki\Title\Title;
use AEWI\SWS\Application\PageReadAuthorizer;
use AEWI\SWS\Domain\Page\PageId;

/**
 * Authorizes or denies every page read, as $allowed says at the time of the read.
 */
class StubPageReadAuthorizer implements PageReadAuthorizer {

	public function __construct(
		public bool $allowed
	) {
	}

	public function authorizeReadByPageId( PageId $pageId ): bool {
		return $this->allowed;
	}

	public function authorizeReadByPageTitle( Title $title ): bool {
		return $this->allowed;
	}

}
