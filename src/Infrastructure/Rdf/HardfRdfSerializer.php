<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Infrastructure\Rdf;

use ProfessionalWiki\SWS\Domain\Rdf\QuadList;
use ProfessionalWiki\SWS\Domain\Rdf\RdfFormat;
use ProfessionalWiki\SWS\Domain\Rdf\RdfSerializer;
use ProfessionalWiki\SWS\Domain\Rdf\RdfStreamWriter;
use Psr\Log\LoggerInterface;

readonly class HardfRdfSerializer implements RdfSerializer {

	/**
	 * @param array<string, string> $prefixes Prefix label to namespace IRI, used for abbreviation.
	 */
	public function __construct(
		private array $prefixes,
		private LoggerInterface $logger,
	) {
	}

	public function serialize( QuadList $quads, RdfFormat $format ): string {
		$writer = $this->newWriter( $format );

		return $writer->write( $quads ) . $writer->finish();
	}

	public function newWriter( RdfFormat $format ): RdfStreamWriter {
		return new HardfRdfStreamWriter( $this->prefixes, $format, $this->logger );
	}

}
