<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\Actions\CreateSubjectPage\CreateSubjectPageRequest;
use AEWI\SWS\Application\Actions\CreateSubjectPage\InvalidPageTitleException;
use AEWI\SWS\Application\Subject\Exception\SubjectIdInUseException;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\CsrfValidator;
use AEWI\SWS\Presentation\DocumentationUrl;
use AEWI\SWS\Presentation\RestCreateSubjectPagePresenter;
use RuntimeException;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Creates a Subject together with a page of its own, in one revision. For the endpoints that add a
 * Subject to a page that already exists, see POST /sws/v0/page/{pageId}/mainSubject and
 * /subjects.
 */
class CreateSubjectPageApi extends SimpleHandler {

	use TakesDependentSubjects;

	public function __construct(
		private readonly CsrfValidator $csrfValidator
	) {
	}

	public function run(): Response {
		$this->csrfValidator->verifyCsrfToken();

		$body = $this->getValidatedBody();

		$presenter = new RestCreateSubjectPagePresenter();

		try {
			SWSExtension::getInstance()
				->newCreateSubjectPageAction( $presenter, $this->getAuthority() )
				->createSubjectPage( new CreateSubjectPageRequest(
					label: $body['label'] ?? null,
					pageTitle: $body['pageTitle'] ?? null,
					schemaName: $body['schema'],
					statements: $body['statements'],
					comment: $body['comment'] ?? null,
					id: $body['id'] ?? null,
					dependentSubjects: $body['dependentSubjects'] ?? [],
                    fieldNotes: $body['fieldNotes'] ?? [],
					identity: $this->identityFromBody( $body ),
				) );
		} catch ( InvalidPageTitleException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
				'pageTitle' => $e->pageTitle,
			] );
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SubjectIdInUseException $e ) {
			return $this->subjectIdInUseResponse( $e );
		} catch ( RuntimeException $e ) {
			return $this->getResponseFactory()->createHttpError( 403, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		$response = $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
		$response->setStatus( $presenter->getStatusCode() );
		return $response;
	}

	public function getBodyParamSettings(): array {
		return [
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Display label for the Entity, which on a page-first wiki also titles '
					. 'the page created for it where no pageTitle is given. Optional: omit it, or pass an empty string, '
					. 'to create a Entity nobody named, whose page is titled after its Entity ID. A label that is not '
					. 'a main-namespace page title titles no page either, and is stored as the label all the same. '
					. 'A subject-first wiki titles every page it creates after the Entity ID.',
			],
			'pageTitle' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Title of the page created, which takes precedence over the label. '
					. 'Optional: omit it, or pass an empty string, to leave the title to the label. A title that '
					. 'names another namespace or is no title at all answers 400 rather than falling back to the '
					. 'label; a title already taken answers 409. A subject-first wiki answers 400 to any title.',
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
