<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\EntryPoints;

use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;

/**
 * Maps a QueryException to the localized message that wikitext-facing surfaces
 * ({{#cypher_raw}} and nw.query()) show to users. Returns the message key and its
 * parameters rather than rendered text, so each surface can render it in its own
 * language context.
 */
readonly class CypherErrorMessage {

	/**
	 * @param string[] $params
	 */
	public function __construct(
		public string $key,
		public array $params,
	) {
	}

	public static function for( QueryException $error ): self {
		return match ( $error->errorType() ) {
			'permissionDenied' => new self( 'sws-cypher-error-permission-denied', [] ),
			'emptyQuery' => new self( 'sws-cypher-error-empty-query', [] ),
			'writeQueryRejected' => new self( 'sws-cypher-error-write-query', [] ),
			'backendUnavailable' => new self( 'sws-cypher-error-backend-unavailable', [] ),
			'cypherSyntaxError' => new self( 'sws-cypher-error-syntax', [ $error->getMessage() ] ),
			'parameterMissing' => new self( 'sws-cypher-error-parameter-missing', [ $error->getMessage() ] ),
			'queryTimeout' => new self( 'sws-cypher-error-timeout', [ $error->getMessage() ] ),
			default => new self( 'sws-cypher-error-internal', [ $error->getMessage() ] ),
		};
	}

}
