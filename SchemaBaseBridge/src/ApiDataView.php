<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ApiBase;
use MediaWiki\Title\Title;

/** Complete current page/subject read model; separate from subject editing APIs. */
final class ApiDataView extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$p = $this->extractRequestParams();
		$title = Title::newFromID( $p['pageid'] );
		if ( $title === null || !$this->getAuthority()->authorizeRead( 'read', $title ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $p['language'] ) !== 1 ) {
			$this->dieWithError( 'swsbasebridge-unavailable', 'unavailable' );
		}
		try {
			$data = ( new DataViewLookup( $this->getContext() ) )->read( $title, $p['language'], $p['subject'] );
		} catch ( \RuntimeException ) {
			$this->dieWithError( 'swsbasebridge-unavailable', 'unavailable' );
		}
		if ( !$this->getResult()->addValue( null, $this->getModuleName(), $data ) ) {
			$this->dieWithError( 'swsbasebridge-unavailable', 'unavailable' );
		}
	}

	public function getAllowedParams(): array {
		return [ 'pageid' => [ self::PARAM_TYPE => 'integer', self::PARAM_MIN => 1, self::PARAM_REQUIRED => true ],
			'subject' => [ self::PARAM_TYPE => 'string' ],
			'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ] ];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
