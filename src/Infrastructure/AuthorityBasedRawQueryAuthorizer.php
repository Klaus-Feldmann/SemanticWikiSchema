<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Infrastructure;

use MediaWiki\Permissions\Authority;
use AEWI\SWS\Application\RawQueryAuthorizer;

class AuthorityBasedRawQueryAuthorizer implements RawQueryAuthorizer {

	public const string RIGHT = 'sws-query';

	public function __construct(
		private readonly Authority $authority,
	) {
	}

	public function authorizeRawQuery(): bool {
		return $this->authority->isAllowed( self::RIGHT );
	}

}
