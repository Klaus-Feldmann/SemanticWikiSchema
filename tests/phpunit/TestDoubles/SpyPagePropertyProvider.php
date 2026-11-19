<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Domain\Page\PagePropertyProvider;
use AEWI\SWS\Domain\Page\PagePropertyProviderContext;

class SpyPagePropertyProvider implements PagePropertyProvider {

	private ?PagePropertyProviderContext $receivedContext = null;

	public function getProperties( PagePropertyProviderContext $context ): array {
		$this->receivedContext = $context;
		return [];
	}

	public function getReceivedContext(): PagePropertyProviderContext {
		if ( $this->receivedContext === null ) {
			throw new \LogicException( 'getProperties was not called' );
		}

		return $this->receivedContext;
	}

}
