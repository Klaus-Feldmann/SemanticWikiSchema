<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\Application\Subject\Exception\SubjectNotFoundException;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\DocumentationUrl;
use AEWI\SWS\Presentation\ViolationSerializer;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectUpdateApi extends SimpleHandler {

	public function run( string $subjectId ): Response {
		$query = SWSExtension::getInstance()->newValidateSubjectUpdateQuery( $this->getAuthority() );
		$body = $this->getValidatedBody();

		try {
			$violations = $query->validate(
				$subjectId,
				$body['statements'],
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
		}

		return $this->getResponseFactory()->createJson( [
			'violations' => ViolationSerializer::serializeMany( $violations ),
		] );
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
				self::PARAM_DESCRIPTION => 'Accepted for parity with PUT /subject/{id} but ignored: a label is '
					. 'optional, so nothing about it can be violated.',
			],
			'fieldNotes' => [ self::PARAM_SOURCE => 'body', ParamValidator::PARAM_TYPE => 'array', ParamValidator::PARAM_REQUIRED => false ],
            'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs) for the proposed update. Shape matches the subject JSON format documented at ' . DocumentationUrl::SubjectFormat->value . '.',
			],
			'comment' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Accepted for parity with PUT /subject/{id} but ignored — this endpoint never persists.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
