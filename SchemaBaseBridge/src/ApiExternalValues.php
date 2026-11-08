<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ApiBase;
use MediaWiki\Title\Title;

/** Current main-subject view only: read the schema, derive values, never write statements. */
final class ApiExternalValues extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$p = $this->extractRequestParams();
		$title = Title::newFromID( $p['pageid'] );
		if ( $title === null || !$this->getAuthority()->authorizeRead( 'read', $title ) ||
			preg_match( '/\A[a-z]{2,12}(?:-[a-z0-9]{1,12})*\z/', $p['language'] ) !== 1 ) {
			$this->dieWithError( 'swsbasebridge-unavailable', 'unavailable' );
		}
		$data = ( new ExternalValuesLookup( $this->getContext() ) )->read( $title, $p );
		$this->getResult()->addValue( null, $this->getModuleName(), $data );
	}

	public function getAllowedParams(): array {
		return [ 'pageid' => [ self::PARAM_TYPE => 'integer', self::PARAM_MIN => 1, self::PARAM_REQUIRED => true ],
			'presentation' => [ self::PARAM_TYPE => 'boolean', self::PARAM_DFLT => false ],
			'subject' => [ self::PARAM_TYPE => 'string', self::PARAM_REQUIRED => true ],
			'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ] ];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
