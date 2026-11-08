<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use MediaWiki\Title\Title;

/** Full local titles, never URLs, interwiki references, fragments, or virtual pages. */
final class LocalPageTitle {
	public static function normalize( string $text ): ?string {
		if ( preg_match( '/[\x00-\x1f\x7f]/', $text ) || str_contains( $text, '#' )
			|| preg_match( '~^[a-z][a-z0-9+.-]*://~i', trim( $text ) ) ) {
			return null;
		}
		$title = Title::newFromText( trim( $text ) );
		return $title === null || $title->isExternal() || !$title->canExist()
			? null : $title->getPrefixedText();
	}
}
