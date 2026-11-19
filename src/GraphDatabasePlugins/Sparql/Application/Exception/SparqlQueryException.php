<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Sparql\Application\Exception;

use RuntimeException;

/**
 * Base for the errors the SPARQL query surfaces classify. Mirrors the Neo4j
 * {@see \AEWI\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException}:
 * each subclass names a stable `errorType()` the REST envelope and the localized wikitext messages
 * branch on.
 */
abstract class SparqlQueryException extends RuntimeException {

	abstract public function errorType(): string;

}
