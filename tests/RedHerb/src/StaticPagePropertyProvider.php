<?php

declare( strict_types = 1 );

namespace AEWI\RedHerb;

use AEWI\SWS\Domain\Page\PagePropertyProvider;
use AEWI\SWS\Domain\Page\PagePropertyProviderContext;

class StaticPagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [
			'redherb_testProperty' => 42,
		];
	}

}
