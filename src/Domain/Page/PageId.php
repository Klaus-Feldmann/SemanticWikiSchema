<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Page;

readonly class PageId {

	public function __construct(
		public int $id
	) {
	}

}
