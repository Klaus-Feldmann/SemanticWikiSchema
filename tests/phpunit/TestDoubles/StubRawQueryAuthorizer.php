<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Application\RawQueryAuthorizer;

/**
 * Authorizes or denies every raw query.
 */
class StubRawQueryAuthorizer implements RawQueryAuthorizer {

	public function __construct(
		private readonly bool $allowed
	) {
	}

	public function authorizeRawQuery(): bool {
		return $this->allowed;
	}

}
