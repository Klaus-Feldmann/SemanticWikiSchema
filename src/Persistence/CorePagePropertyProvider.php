<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Persistence;

use AEWI\SWS\Domain\Page\PageDateTime;
use AEWI\SWS\Domain\Page\PageProperties;
use AEWI\SWS\Domain\Page\PagePropertyProvider;
use AEWI\SWS\Domain\Page\PagePropertyProviderContext;

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
