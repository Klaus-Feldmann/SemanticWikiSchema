<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\RestGetPageSubjectsPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetPageSubjectsApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	private const string EXPAND_SCHEMAS = 'schemas';
	private const string EXPAND_RELATIONS = 'relations';

	public function run( int $pageId ): Response {
		$presenter = new RestGetPageSubjectsPresenter();

		$expandOptions = explode( '|', $this->getRequest()->getQueryParams()['expand'] ?? '' );

		SWSExtension::getInstance()->newGetPageSubjectsQuery( $presenter, $this->getAuthority() )->execute(
			pageId: $pageId,
			includeSchemas: in_array( self::EXPAND_SCHEMAS, $expandOptions, true ),
			includeReferencedSubjects: in_array( self::EXPAND_RELATIONS, $expandOptions, true ),
		);

		return $this->getResponseFactory()->createJson( $presenter->getJsonArray() );
	}

	public function getParamSettings(): array {
		return [
			'pageId' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				self::PARAM_DESCRIPTION => 'MediaWiki page ID.',
			],
			'expand' => [
				self::PARAM_SOURCE => 'query',
				ParamValidator::PARAM_TYPE => [
					self::EXPAND_SCHEMAS,
					self::EXPAND_RELATIONS,
				],
				ParamValidator::PARAM_ISMULTI => true,
				ParamValidator::PARAM_REQUIRED => false,
				self::PARAM_DESCRIPTION => 'Embed related data in the response. Accepted values: "schemas", "relations".',
			],
		];
	}

}
