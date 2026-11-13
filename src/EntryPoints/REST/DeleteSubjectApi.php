<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\CsrfValidator;
use Wikimedia\ParamValidator\ParamValidator;

class DeleteSubjectApi extends SimpleHandler {

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $subjectId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		// getValidatedBody() returns null when the request has no body.
		$body = $this->getValidatedBody() ?? [];
		$comment = $body['comment'] ?? null;

		$extension = SWSExtension::getInstance();
		$id = $extension->getSubjectIdParser( true )->parse( $subjectId );

		if ( $id === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => "Subject ID has the wrong format: '$subjectId'",
			] );
		}

		try {
			$extension->newDeleteSubjectAction( $this->getAuthority() )->deleteSubject(
				$id,
				$comment
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

		return new Response();
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject: E followed by its positive number, an existing legacy s-ID, or sourceKey:localId for another Source.',
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
