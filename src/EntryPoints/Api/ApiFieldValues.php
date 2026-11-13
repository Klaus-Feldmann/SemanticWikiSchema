<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Api;

use ApiBase;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\EffectiveFieldLookup;

/** Read-only effective field contract shared with mw.sws.collectFields and #sws_value. */
final class ApiFieldValues extends ApiBase {
	public function execute(): void {
		$this->getMain()->setCacheMode( 'private' );
		$p = $this->extractRequestParams();
		if ( ( isset( $p['page'] ) ? 1 : 0 ) + ( isset( $p['subject'] ) ? 1 : 0 ) !== 1 ) {
			$this->dieWithError( 'sws-fields-invalid-target', 'invalidtarget' );
		}
		$title = isset( $p['page'] ) ? Title::newFromText( $p['page'] ) : Title::newMainPage();
		if ( $title === null ) { $this->dieWithError( 'sws-fields-unavailable', 'unavailable' ); }
		try { $data = ( new EffectiveFieldLookup( $this->getContext(), canonicalId: fn ( $id ) => \ProfessionalWiki\SWS\SWSExtension::getInstance()->newIdentityBoundaryResolver()->entity( $id ) ) )->read( $title, array_filter( $p, static fn ( $v ) => $v !== null ) ); }
		catch ( \InvalidArgumentException|\RuntimeException ) { $this->dieWithError( 'sws-fields-unavailable', 'unavailable' ); }
		$this->getResult()->addValue( null, $this->getModuleName(), $data );
	}
	public function getAllowedParams(): array {
		return [ 'page' => [ self::PARAM_TYPE => 'string' ], 'subject' => [ self::PARAM_TYPE => 'string' ],
			'field' => [ self::PARAM_TYPE => 'string' ], 'origin' => [ self::PARAM_TYPE => [ 'all', 'own', 'external' ], self::PARAM_DFLT => 'all' ],
			'includeImages' => [ self::PARAM_TYPE => 'boolean', self::PARAM_DFLT => false ],
			'language' => [ self::PARAM_TYPE => 'string', self::PARAM_DFLT => $this->getLanguage()->getCode() ] ];
	}
	public function isWriteMode(): bool { return false; }
	protected function getExamplesMessages(): array { return []; }
}
