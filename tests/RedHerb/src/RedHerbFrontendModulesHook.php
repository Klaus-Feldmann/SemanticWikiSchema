<?php

declare( strict_types = 1 );

namespace AEWI\RedHerb;

use MediaWiki\Output\OutputPage;
use AEWI\SWS\EntryPoints\Hook\SWSGetFrontendModulesHook;
use Skin;

class RedHerbFrontendModulesHook implements SWSGetFrontendModulesHook {

	public function onSWSGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.redherb';
		$modules[] = 'ext.redherb-create-subject';
		$modules[] = 'ext.redherb-edit-main-subject';
	}

}
