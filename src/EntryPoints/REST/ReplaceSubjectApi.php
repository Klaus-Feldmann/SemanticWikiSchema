<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\CsrfValidator;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;
use ProfessionalWiki\SWS\Presentation\RestReplaceSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class ReplaceSubjectApi extends SimpleHandler {

	use TakesDependentSubjects;

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	/**
	 * @throws HttpException
	 */
	public function run( string $subjectId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		$body = $this->getValidatedBody();

		$presenter = new RestReplaceSubjectPresenter();

		try {
			$extension = SWSExtension::getInstance();
			$extension->newReplaceSubjectAction( $presenter, $this->getAuthority() )->replace(
				$extension->getSubjectIdParser()->parseOrThrow( $subjectId ),
				$body['label'] ?? null,
				$body['statements'],
				$body['comment'] ?? null,
				$body['dependentSubjects'] ?? []
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectIdInUseException $e ) {
			return $this->subjectIdInUseResponse( $e );
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
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Subject: 15 characters starting with "s" for a Subject of this wiki, or "sourceKey:localId" for one from another Source.',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'New display label. Replaces the stored one entirely: omitting it, or '
					. 'passing an empty string, leaves the Subject with no label, just as an omitted property name '
					. 'deletes its Statement.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Map of property names to Statements. Replaces the Subject\'s statement list entirely; omitted property names are deleted. Pass `{}` to delete all statements. Nested shape matches the subject JSON format documented at ' . DocumentationUrl::SubjectFormat->value . '.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		] + $this->dependentSubjectsParamSettings();
	}

}
