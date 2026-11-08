<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Hook;

final class ImageConfigHook {
	public static function onMakeGlobalVariablesScript( array &$vars, $out ): void {
		$vars['wgSWSProjectName'] = (string)$out->getConfig()->get( 'Sitename' );
	}
}
