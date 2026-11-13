<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use MediaWiki\Rest\Response;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\ParamValidator\ParamValidator;

final class ExportSubjectDataViewApi extends DataViewExportApi {
	public function run( string $subjectId ): Response {
		$extension = SWSExtension::getInstance();
		$id = $extension->getSubjectIdParser( true )->parse( $subjectId );
		if ( $id === null ) { return $this->getResponseFactory()->createHttpError( 400, [ 'message' => 'Invalid Subject ID.' ] ); }
		$page = $extension->newSubjectHostingPageResolver( $this->getAuthority() )->resolveReadableHostingPage( $id );
		$title = $page === null ? null : Title::newFromID( $page->getId()->id );
		return $title === null ? $this->unavailable() : $this->exportView( $title, $id->text );
	}
	public function getParamSettings(): array {
		return [ 'subjectId' => [ self::PARAM_SOURCE => 'path', ParamValidator::PARAM_TYPE => 'string',
			ParamValidator::PARAM_REQUIRED => true ] ] + $this->commonParams();
	}
}
