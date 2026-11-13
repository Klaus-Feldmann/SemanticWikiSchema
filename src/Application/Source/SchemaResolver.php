<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Source;

use Psr\Log\LoggerInterface;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceResolver;
use ProfessionalWiki\SWS\Domain\Source\SourceRegistry;

/**
 * Resolves a Schema through the Source it is referenced from (ADR 23), which need not be the Source of
 * the Subject using it.
 *
 * A reference to a Source this wiki does not have resolves to no Schema, logged as a warning, the same
 * as a Schema that was never created: callers already report an unresolvable Schema as a degraded state
 * rather than failing.
 */
readonly class SchemaResolver implements SchemaReferenceResolver {

	public function __construct(
		private SourceRegistry $sourceRegistry,
		private LoggerInterface $logger,
        private ?\Closure $canonicalize = null,
	) {
	}

	public function getSchema( SchemaReference $reference ): ?Schema {
        $reference = $this->canonicalize === null ? $reference : ( $this->canonicalize )( $reference );
		$source = $this->sourceRegistry->getSource(
			$reference->source ?? $this->sourceRegistry->getLocalSourceKey()
		);

		if ( $source === null ) {
			$this->logger->warning(
				'SWS: no registered Source for Schema reference: ' . $reference->getText()
			);
			return null;
		}

		$schema = $source->getSchema( $reference->name );
		// An ID reference cannot resolve to an old, merely same-named Schema page.
		if ( $reference->id !== null && ( $schema?->getId() === null || !$reference->id->equals( $schema->getId() ) ) ) { return null; }
		return $schema;
	}

}
