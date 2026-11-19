<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use AEWI\SWS\Application\Subject\Exception\SubjectIdInUseException;
use AEWI\SWS\Presentation\DocumentationUrl;
use Wikimedia\ParamValidator\ParamValidator;

trait TakesDependentSubjects {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function identityFromBody( array $body ): ?array {
		return \AEWI\SWS\Domain\Identity\LocalizedIdentity::fromRequest( $body );
	}

	private function identityParamSettings(): array {
		return [
			'identityLanguage'=>[ self::PARAM_SOURCE=>'body', ParamValidator::PARAM_TYPE=>'string', ParamValidator::PARAM_REQUIRED=>false ],
			'labels'=>[ self::PARAM_SOURCE=>'body', ParamValidator::PARAM_TYPE=>'array', ParamValidator::PARAM_REQUIRED=>false ],
			'descriptions'=>[ self::PARAM_SOURCE=>'body', ParamValidator::PARAM_TYPE=>'array', ParamValidator::PARAM_REQUIRED=>false ],
		];
	}

	private function dependentSubjectsParamSettings(): array {
		return [
			'dependentSubjects' => [
				self::PARAM_SOURCE => 'body',
				ParamValidator::PARAM_TYPE => 'array',
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Dependent Entities to write in the same revision, each an object with '
					. 'an `id` (pre-minted with POST /sws/v0/subject-ids), a `schema` whose Entities are '
					. 'dependent, and `statements`. When replacing a Entity, the `id` of a Dependent Entity of the '
					. 'same Schema that it points at, directly or through other Dependent Entities, replaces that '
					. 'Dependent Entity; any other `id` in use answers 409. See '
					. DocumentationUrl::SubjectFormat->value . '.',
			],
		];
	}

	private function subjectIdInUseResponse( SubjectIdInUseException $exception ): Response {
		return $this->getResponseFactory()->createHttpError( 409, [
			'status' => 'error',
			'message' => $exception->getMessage(),
		] );
	}

}
