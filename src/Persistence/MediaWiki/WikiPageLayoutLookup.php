<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use InvalidArgumentException;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use ProfessionalWiki\SWS\Application\LayoutLookup;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\Domain\Layout\Layout;
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;
use ProfessionalWiki\SWS\EntryPoints\Content\LayoutContent;
use ProfessionalWiki\SWS\SWSExtension;

class WikiPageLayoutLookup implements LayoutLookup {

	public function __construct(
		private readonly PageContentFetcher $pageContentFetcher,
		private readonly Authority $authority,
		private readonly LayoutPersistenceDeserializer $layoutDeserializer,
		private readonly TitleFactory $titleFactory,
		private readonly PageReadAuthorizer $readAuthorizer,
	) {
	}

	public function getLayout( LayoutName $layoutName ): ?Layout {
		$title = $this->titleFactory->newFromText( $layoutName->getText(), SWSExtension::NS_LAYOUT );

		if ( $title === null || !$title->exists() ) {
			return null;
		}

		// The audience check inside the content fetcher filters revision deletion only; this
		// is the sole per-title read gate on the Layout read path. Denial is null, the same
		// as an absent Layout (#1046).
		if ( !$this->readAuthorizer->authorizeReadByPageTitle( $title ) ) {
			return null;
		}

		$content = $this->getContent( $title );

		if ( $content === null ) {
			return null;
		}

		try {
			return $this->layoutDeserializer->deserialize( $layoutName, $content->getText() );
		}
		catch ( InvalidArgumentException ) {
			return null;
		}
	}

	private function getContent( Title $title ): ?LayoutContent {
		$content = $this->pageContentFetcher->getPageContent(
			$title,
			$this->authority,
			SWSExtension::NS_LAYOUT
		);

		if ( $content instanceof LayoutContent ) {
			return $content;
		}

		if ( $content === null ) {
			return null;
		}

		throw new \LogicException( 'Unexpected content type: not a LayoutContent' );
	}

}
