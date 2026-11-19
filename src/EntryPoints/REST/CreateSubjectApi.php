<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\Actions\CreateSubject\CreateSubjectRequest;
use AEWI\SWS\Application\Subject\Exception\SubjectIdInUseException;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\CsrfValidator;
use AEWI\SWS\Presentation\DocumentationUrl;
use AEWI\SWS\Presentation\RestCreateSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class CreateSubjectApi extends SimpleHandler {

	use TakesDependentSubjects;

	public function __construct(
		private readonly bool $isMainSubject,
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run( int $pageId ): Response {
		$this->csrfValidator->verifyCsrfToken();

		$body = $this->getValidatedBody();

		$presenter = new RestCreateSubjectPresenter();

		try {
			SWSExtension::getInstance()->newCreateSubjectAction( $presenter, $this->getAuthority() )->createSubject(
				new CreateSubjectRequest(
					pageId: $pageId,
					isMainSubject: $this->isMainSubject,
					label: $body['label'] ?? null,
					schemaName: $body['schema'],
					statements: $body['statements'],
					comment: $body['comment'] ?? null,
					id: $body['id'] ?? null,
					dependentSubjects: $body['dependentSubjects'] ?? [],
                    fieldNotes: $body['fieldNotes'] ?? [],
					identity: $this->identityFromBody( $body ),
				)
			);
		} catch ( \InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectIdInUseException $e ) {
			return $this->subjectIdInUseResponse( $e );
		} catch ( \RuntimeException $e ) {
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
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
		];
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Display label for the Entity. Optional: omit it, or pass an empty '
					. 'string, to create a Entity nobody named. Such a Entity is displayed under its page name '
					. 'when it is the page\'s main Entity, and under its Schema name otherwise.',
			],
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the Schema this Entity is an instance of.',
			],
			'fieldNotes' => [ self::PARAM_SOURCE => 'body', ParamValidator::PARAM_TYPE => 'array', ParamValidator::PARAM_REQUIRED => false ],
            'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'List of Statements (property/value pairs) for the Entity. Nested shape matches the subject JSON format documented at ' . DocumentationUrl::SubjectFormat->value . '.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional edit summary.',
			],
			'id' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Optional Entity ID to assign. Must be a well-formed, unused '
					. 'Entity ID (malformed is rejected with 400, in-use with 409). When omitted, the server '
					. 'mints one. Pre-mint IDs with POST /sws/v0/subject-ids to wire relations across a batch.',
			],
		] + $this->dependentSubjectsParamSettings() + $this->identityParamSettings();
	}

}
