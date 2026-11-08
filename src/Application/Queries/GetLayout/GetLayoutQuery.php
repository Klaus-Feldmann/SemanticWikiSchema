<?php

declare( strict_types=1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetLayout;

use ProfessionalWiki\SWS\Application\LayoutLookup;
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;
use ProfessionalWiki\SWS\Presentation\LayoutPresentationSerializer;

readonly class GetLayoutQuery {

	public function __construct(
		private GetLayoutPresenter $presenter,
		private LayoutLookup $layoutLookup,
		private LayoutPresentationSerializer $serializer,
	) {
	}

	public function execute( string $layoutName ): void {
		$layout = $this->layoutLookup->getLayout( new LayoutName( $layoutName ) );

		if ( $layout === null ) {
			$this->presenter->presentLayoutNotFound();
			return;
		}

		$this->presenter->presentLayout( $this->serializer->serialize( $layout ) );
	}

}
