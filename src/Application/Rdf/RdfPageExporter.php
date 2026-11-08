<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Rdf;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Rdf\RdfFormat;
use ProfessionalWiki\SWS\Domain\Rdf\RdfSerializer;

/**
 * Exports a single page as a self-contained RDF document. Returns null when there is no page to export,
 * so callers can map that to a 404.
 */
readonly class RdfPageExporter {

	public function __construct(
		private RdfPageLoader $loader,
		private PageProjector $projector,
		private RdfSerializer $serializer,
	) {
	}

	public function exportByPageId( PageId $pageId, RdfFormat $format ): ?string {
		$page = $this->loader->loadByPageId( $pageId );

		if ( $page === null ) {
			return null;
		}

		return $this->serializer->serialize( $this->projector->projectPage( $page ), $format );
	}

}
