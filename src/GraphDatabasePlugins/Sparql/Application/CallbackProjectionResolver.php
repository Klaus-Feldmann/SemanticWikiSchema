<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Sparql\Application;

use Closure;
use AEWI\SWS\Application\Rdf\RdfProjection;

/**
 * A {@see ProjectionResolver} backed by two closures, so {@see \AEWI\SWS\SWSExtension}
 * can supply the resolution seam to the store without handing it a reference to itself.
 */
readonly class CallbackProjectionResolver implements ProjectionResolver {

	/**
	 * @param Closure(string): ?RdfProjection $resolveProjection
	 * @param Closure(): string[] $knownProjectionNames
	 */
	public function __construct(
		private Closure $resolveProjection,
		private Closure $knownProjectionNames,
	) {
	}

	public function newRdfProjection( string $projectionName ): ?RdfProjection {
		return ( $this->resolveProjection )( $projectionName );
	}

	public function getRdfProjectionNames(): array {
		return ( $this->knownProjectionNames )();
	}

}
