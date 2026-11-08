<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Rest\Response;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;

final class ExportPageDataViewApi extends DataViewExportApi {
	public function run( int $pageId ): Response {
		$title = Title::newFromID( $pageId );
		return $title === null ? $this->unavailable() : $this->exportView( $title );
	}
	public function getParamSettings(): array {
		return [ 'pageId' => [ self::PARAM_SOURCE => 'path', ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true ] ] + $this->commonParams();
	}
}
