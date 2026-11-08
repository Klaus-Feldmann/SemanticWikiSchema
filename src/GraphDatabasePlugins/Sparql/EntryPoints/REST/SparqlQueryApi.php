<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\REST;

use MediaWiki\MediaWikiServices;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\EmptySparqlQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\InternalSparqlQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlQueryPermissionDeniedException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlStoreUnavailableException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\Exception\SparqlSyntaxException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\SparqlQueryRequest;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\SparqlQueryService;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * POST /sws/v0/query/sparql — runs a read-only SPARQL query against the first configured SPARQL
 * store and returns the W3C `application/sparql-results+json` document unmodified. The read-side sibling
 * of {@see \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\REST\CypherQueryApi}; it
 * reuses the same `sws-query` right and rate limit, since both are read-only graph queries.
 */
class SparqlQueryApi extends SimpleHandler {

	public function __construct(
		private readonly SparqlQueryService $queryService,
	) {
	}

	public function run(): Response {
		$user = MediaWikiServices::getInstance()->getUserFactory()->newFromAuthority( $this->getAuthority() );

		if ( $user->pingLimiter( 'sws-query' ) ) {
			return $this->errorResponse( 429, 'rateLimitExceeded', 'Query rate limit exceeded.' );
		}

		$body = $this->getValidatedBody();

		try {
			$limits = SparqlQueryLimits::forUser( $user );
		} catch ( Throwable $e ) {
			return $this->errorResponse( 500, 'internalError', $e->getMessage() );
		}

		try {
			$result = $this->queryService->execute(
				new SparqlQueryRequest(
					sparql: $body['query'],
					limits: $limits,
				)
			);
		} catch ( SparqlQueryException $e ) {
			return $this->mapException( $e );
		}

		// The W3C results document is returned unmodified, with no SWS envelope around it.
		return $this->getResponseFactory()->createJson( $result->document );
	}

	private function mapException( SparqlQueryException $e ): Response {
		$status = match ( true ) {
			$e instanceof SparqlQueryPermissionDeniedException => 403,
			$e instanceof EmptySparqlQueryException => 400,
			$e instanceof SparqlSyntaxException => 400,
			$e instanceof SparqlStoreUnavailableException => 503,
			$e instanceof InternalSparqlQueryException => 500,
			// Defensive default for any future SparqlQueryException subclass added without updating this map.
			default => 500,
		};

		return $this->errorResponse( $status, $e->errorType(), $e->getMessage() );
	}

	private function errorResponse( int $status, string $errorType, string $message ): Response {
		$response = $this->getResponseFactory()->createJson( [
			'errorType' => $errorType,
			'message' => $message,
		] );
		$response->setStatus( $status );
		return $response;
	}

	public function getBodyParamSettings(): array {
		return [
			'query' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'A read-only SPARQL 1.1 SELECT or ASK query.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
