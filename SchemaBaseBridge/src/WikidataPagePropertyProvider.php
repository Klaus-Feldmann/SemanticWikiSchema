<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\Domain\Page\PagePropertyProvider;
use ProfessionalWiki\SWS\Domain\Page\PagePropertyProviderContext;

final class WikidataPagePropertyProvider implements PagePropertyProvider {
	public function getProperties( PagePropertyProviderContext $context ): array {
		$value = $context->parserProperties['unlinkedwikibase_id'] ?? null;
		return is_string( $value ) && preg_match( '/\AQ[1-9][0-9]*\z/', $value ) === 1
			? [ 'aewiWikidataId' => $value ] : [];
	}
}
