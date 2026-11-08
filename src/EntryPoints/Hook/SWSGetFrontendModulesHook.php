<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\EntryPoints\Hook;

use MediaWiki\Output\OutputPage;
use Skin;

/**
 * Hook interface for extensions to declare ResourceLoader modules that SWS
 * should load alongside ext.sws. Implementations should append module names
 * to $modules. $out and $skin are provided for conditional loading (e.g.
 * per-namespace or per-user).
 */
interface SWSGetFrontendModulesHook {

	/**
	 * @param string[] &$modules
	 */
	public function onSWSGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void;

}
