<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use InvalidArgumentException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\Application\Schema\Exception\SchemaNotFoundException;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\DocumentationUrl;
use ProfessionalWiki\SWS\Presentation\ViolationSerializer;
use Wikimedia\ParamValidator\ParamValidator;

class ValidateSubjectApi extends SimpleHandler {

	public function run(): Response {
		$query = SWSExtension::getInstance()->newValidateSubjectQuery( $this->getAuthority() );
		$body = $this->getValidatedBody();

		try {
			$violations = $query->validate(
				$body['schema'],
				$body['statements'],
			);
		} catch ( InvalidArgumentException $e ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		} catch ( SchemaNotFoundException $e ) {
			return $this->getResponseFactory()->createHttpError( 404, [
				'status' => 'error',
				'message' => $e->getMessage(),
			] );
		}

		return $this->getResponseFactory()->createJson( [
			'violations' => ViolationSerializer::serializeMany( $violations ),
		] );
	}

	public function getBodyParamSettings(): array {
		return [
			'schema' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Name of the Schema to validate the proposed Subject against.',
			],
			'label' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Accepted for parity with the create endpoints but ignored: a label is '
					. 'optional, so nothing about it can be violated.',
			],
			'statements' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Proposed Statements (property/value pairs). Shape matches the subject JSON format documented at ' . DocumentationUrl::SubjectFormat->value . '.',
			],
		];
	}

	public function needsWriteAccess(): bool {
		return false;
	}

}
