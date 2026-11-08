<?php

declare( strict_types = 1 );
namespace SWSBaseBridge;

use ProfessionalWiki\SWS\EntryPoints\SWSRegistrar;

final class Hooks {
	public static function onSWSRegistration( SWSRegistrar $registrar ): void {
		$registrar->addPagePropertyProvider( new WikidataPagePropertyProvider() );
	}
	public static function onScribuntoExternalLibraries( string $engine, array &$libraries ): bool {
		if ( $engine === 'lua' && \ProfessionalWiki\SWS\SWSExtension::getInstance()->isLuaEnabled() ) {
			$libraries['mw.swsbase'] = ScribuntoLuaLibrary::class;
		}
		return true;
	}

	public static function onBeforePageDisplay( \MediaWiki\Output\OutputPage $out ): void {
		$out->addJsConfigVars( 'wgSWSCompleteExport', true );
	}
}
