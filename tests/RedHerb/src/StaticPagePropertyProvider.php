<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\RedHerb;

use ProfessionalWiki\SWS\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderContext;

class StaticPagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [
			'redherb_testProperty' => 42,
		];
	}

}
