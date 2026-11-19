<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\Page;

readonly class PageId {

	public function __construct(
		public int $id
	) {
	}

}
