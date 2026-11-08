<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\REST;

use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\RestGetMainSubjectPresenter;
use Wikimedia\ParamValidator\ParamValidator;

class GetMainSubjectApi extends SimpleHandler {

	use ReadOnlyEndpoint;

	public function run( int $pageId ): Response {
		$presenter = new RestGetMainSubjectPresenter();

		SWSExtension::getInstance()->newGetMainSubjectQuery( $presenter, $this->getAuthority() )->execute( $pageId );

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
		];
	}

}
