<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence;

use ProfessionalWiki\SWS\Domain\Page\PageDateTime;
use ProfessionalWiki\SWS\Domain\Page\PageProperties;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderContext;

class CorePagePropertyProvider implements PagePropertyProvider {

	public function getProperties( PagePropertyProviderContext $context ): array {
		return [
			PageProperties::NAME => $context->pageTitle,
			'namespaceId' => $context->namespaceId,
			'creationTime' => new PageDateTime( $context->creationTime ),
			'lastUpdated' => new PageDateTime( $context->modificationTime ),
			'categories' => $context->categories,
			'lastEditor' => $context->lastEditor,
		];
	}

}
