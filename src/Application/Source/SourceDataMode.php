<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Source;

use MediaWiki\MediaWikiServices;

/** Persistent entity data is the default; the previous cache path remains selectable. */
final class SourceDataMode {

	public static function current(): string {
		if ( \ProfessionalWiki\SWS\Application\IdentityMigration\MigrationWriteScope::active() ) { return 'store'; }
		$mode = MediaWikiServices::getInstance()->getMainConfig()->get( 'SWSSourceDataMode' );
		if ( !in_array( $mode, [ 'cache', 'store' ], true ) ) {
			throw new \UnexpectedValueException( 'SWSSourceDataMode must be cache or store.' );
		}
		return $mode;
	}

	public static function usesStore(): bool {
		return self::current() === 'store';
	}

}
