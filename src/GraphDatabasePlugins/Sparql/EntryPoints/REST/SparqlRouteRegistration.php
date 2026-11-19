<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Sparql\EntryPoints\REST;

use AEWI\SWS\SWSConfig;

/**
 * The read-side counterpart to
 * {@see \AEWI\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\REST\Neo4jRouteRegistration}:
 * contributes the SPARQL query route only when at least one SPARQL store is configured.
 */
class SparqlRouteRegistration {

	/**
	 * REST route files the SPARQL plugin contributes given the raw `SWSSparqlStores` config.
	 *
	 * @return string[]
	 */
	public static function routeFiles( mixed $rawStores ): array {
		if ( !SWSConfig::hasConfiguredSparqlStore( $rawStores ) ) {
			return [];
		}

		return [ __DIR__ . '/sparqlRoutes.json' ];
	}

}
