<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use AEWI\SWS\Application\Subject\Exception\SubjectNotFoundException;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\CsrfValidator;
use AEWI\SWS\Presentation\RestUpdateStatementPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class RemoveStatementApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $subjectId, string $propertyName ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// getValidatedBody() returns null when the request has no body.
		$body = $this->getValidatedBody() ?? [];

		$presenter = new RestUpdateStatementPresenter();

		try {
			$extension = SWSExtension::getInstance();
			$extension->newUpdateStatementAction( $presenter, $this->getAuthority() )->removeStatement(
				$extension->getSubjectIdParser( true )->parseOrThrow( $subjectId ),
				new PropertyName( $propertyName ),
				$body['comment'] ?? null
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectNotFoundException $e ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectEditNotAuthorizedException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Entity: E followed by its positive number, an existing legacy s-ID, or sourceKey:localId for another Source.',
			],
			'propertyName' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the property whose Statement is removed, URL-encoded.',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		];
	}

}
