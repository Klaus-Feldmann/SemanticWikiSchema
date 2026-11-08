<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderContext;

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
