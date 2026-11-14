<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Presentation;

/** Match the client policy: reader language, core fallback chain, wiki content language. */
final class FieldLabelLanguages {
	public static function forReader( string $language ): array {
		$fallbacks = []; $content = (string)( $GLOBALS['wgLanguageCode'] ?? 'en' );
		if ( class_exists( \MediaWiki\MediaWikiServices::class ) ) {
			$services = \MediaWiki\MediaWikiServices::getInstance();
			if ( method_exists( $services, 'getContentLanguage' ) ) { $content = $services->getContentLanguage()->getCode(); }
			if ( method_exists( $services, 'getLanguageFallback' ) ) { $fallbacks = $services->getLanguageFallback()->getAll( $language ); }
		}
		return array_values( array_unique( [ strtolower( $language ), ...array_map( 'strtolower', $fallbacks ), strtolower( $content ) ] ) );
	}
}
