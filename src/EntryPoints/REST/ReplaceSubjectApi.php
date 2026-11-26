<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use InvalidArgumentException;
use AEWI\SWS\Application\Subject\Exception\SubjectRevisionConflictException;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use AEWI\SWS\Application\Subject\Exception\SubjectIdInUseException;
use AEWI\SWS\Application\Subject\Exception\SubjectNotFoundException;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\CsrfValidator;
use AEWI\SWS\Presentation\DocumentationUrl;
use AEWI\SWS\Presentation\RestReplaceSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class ReplaceSubjectApi extends SimpleHandler {

	use TakesDependentSubjects;

	public const SUPPORTS_BASE_REVISION = true;

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
				$extension->getSubjectIdParser( true )->parseOrThrow( $subjectId ),
				$body['label'] ?? null,
				$body['statements'],
				$body['comment'] ?? null,
				$body['dependentSubjects'] ?? [],
                $body['fieldNotes'] ?? null,
				$this->identityFromBody( $body ),
				$body['fieldKeyVersion'] ?? null,
				$body['baseRevisionId'] ?? null
			);
		} catch ( SubjectRevisionConflictException ) {
			return $this->getResponseFactory()->createHttpError( 409, [
				'status' => 'error',
				'message' => 'The page changed after the supplied base revision. Reload the entity before editing.',
			] );
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
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Entity: E followed by its positive number, an existing legacy s-ID, or sourceKey:localId for another Source.',
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
					. 'passing an empty string, leaves the Entity with no label, just as an omitted property name '
					. 'deletes its Statement.',
			],
			'baseRevisionId' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				IntegerDef::PARAM_MIN => 1,
			],
			'fieldKeyVersion' => [ self::PARAM_SOURCE => 'body', ParamValidator::PARAM_TYPE => 'integer', ParamValidator::PARAM_REQUIRED => false ],
			'fieldNotes' => [ self::PARAM_SOURCE => 'body', ParamValidator::PARAM_TYPE => 'array', ParamValidator::PARAM_REQUIRED => false ],
            'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Map of property names to Statements. Replaces the Entity\'s statement list entirely; omitted property names are deleted. Pass `{}` to delete all statements. Nested shape matches the subject JSON format documented at ' . DocumentationUrl::SubjectFormat->value . '.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
		] + $this->dependentSubjectsParamSettings() + $this->identityParamSettings();
	}

}
