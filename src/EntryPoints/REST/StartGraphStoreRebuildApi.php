<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\SWS\Application\GraphRebuild\RebuildAlreadyRunningException;
use ProfessionalWiki\SWS\Application\GraphRebuild\UnknownGraphStoreException;
use ProfessionalWiki\SWS\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Application\GraphRebuild\RebuildStartLockUnavailableException;
use ProfessionalWiki\SWS\Presentation\CsrfValidator;
use ProfessionalWiki\SWS\Presentation\GraphStoreStatusSerializer;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Queues a rebuild of one graph store. Answers as soon as the rebuild is filed, not when it is done:
 * a rebuild of a large wiki outlives any request, so what comes back is the run to watch.
 */
class StartGraphStoreRebuildApi extends SimpleHandler {

	use GraphStoreAdminAccess;

	public function __construct(
		private readonly CsrfValidator $csrfValidator,
	) {
	}

	public function run( string $name ): Response {
		$refusal = $this->refuseWithoutTheAdminRight() ?? $this->refuseWhileTheWikiIsReadOnly();

		if ( $refusal !== null ) {
			return $refusal;
		}

		$this->csrfValidator->verifyCsrfToken();

		try {
			$run = SWSExtension::getInstance()
				->newGraphRebuildCoordinator( GraphRebuildCoordinator::BACKGROUND_BATCH_SIZE )
				->startBackground( $name, RebuildTrigger::Api );
		} catch ( UnknownGraphStoreException $e ) {
			return $this->errorResponse( 404, 'unknownStore', $e->getMessage() );
		} catch ( RebuildAlreadyRunningException $e ) {
			return $this->errorResponse( 409, 'rebuildAlreadyRunning', $e->getMessage() );
		} catch ( RebuildStartLockUnavailableException $e ) {
			// Something else is starting a rebuild of this store right now. Not an error to report to a
			// person: the same call a moment later is the answer.
			return $this->errorResponse( 409, 'rebuildBeingStarted', $e->getMessage() );
		}

		$response = $this->getResponseFactory()->createJson( ( new GraphStoreStatusSerializer() )->runToArray( $run ) );
		$response->setStatus( 202 );

		return $response;
	}

	public function getParamSettings(): array {
		return [
			'name' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the graph store to rebuild, as configured.',
			],
		];
	}

}
