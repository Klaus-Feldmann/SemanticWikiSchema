<?php

declare( strict_types = 1 );

namespace AEWI\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Presentation\RestGetReferencingSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class GetReferencingSubjectsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( string $subjectId ): Response {
		// Validate the ID shape first, so a malformed value is a clean 400 rather than becoming a 500.
		if ( SWSExtension::getInstance()->getSubjectIdParser()->parse( $subjectId ) === null ) {
			return $this->getResponseFactory()->createHttpError( 400, [
				'message' => wfMessage('sws-api-invalid-entity-id', $subjectId)->text(),
			] );
		}

		$presenter = new RestGetReferencingSubjectsPresenter();

		SWSExtension::getInstance()
			->newGetReferencingSubjectsQuery( $presenter, $this->getAuthority() )
			->execute( subjectId: $subjectId, limit: $this->getValidatedParams()['limit'] );

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'subjectId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'Persistent identifier of the Entity the returned Entities point at.',
			],
			'limit' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => 10,
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => 50,
				self::PARAM_DESCRIPTION => 'Maximum number of referencing Entities to return.',
			],
		];
	}

}
