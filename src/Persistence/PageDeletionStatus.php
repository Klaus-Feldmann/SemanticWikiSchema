<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence;

class PageDeletionStatus {

	public function __construct(
		public readonly bool $succeeded,
		public readonly ?string $errorMessage = null,
	) {
	}

}
