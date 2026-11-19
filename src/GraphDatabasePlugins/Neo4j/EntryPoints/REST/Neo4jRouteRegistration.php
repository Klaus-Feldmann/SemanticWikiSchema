<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\REST;

use AEWI\SWS\SWSConfig;

class Neo4jRouteRegistration {

	/**
	 * REST route files the Neo4j plugin contributes given the resolved Bolt URLs.
	 *
	 * @return string[]
	 */
	public static function routeFiles( ?string $readUrl, ?string $writeUrl ): array {
		if ( !SWSConfig::neo4jConfigured( $readUrl, $writeUrl ) ) {
			return [];
		}

		return [ __DIR__ . '/neo4jRoutes.json' ];
	}

}
