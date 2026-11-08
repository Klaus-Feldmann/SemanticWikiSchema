<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderContext;

class StubPagePropertyProvider implements PagePropertyProvider {

	/**
	 * @param array<string, mixed> $properties
	 */
	public function __construct(
		private readonly array $properties,
	) {
	}

	public function getProperties( PagePropertyProviderContext $context ): array {
		return $this->properties;
	}

}
