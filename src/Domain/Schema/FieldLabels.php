<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema;

/** Presentation only: a label must never resolve or rename a technical field key. */
final class FieldLabels {
	public static function display( array $labels, string $key, array $languages ): string {
		foreach ( array_unique( array_map( 'strtolower', $languages ) ) as $language ) {
			if ( isset( $labels[$language] ) && is_string( $labels[$language] ) && trim( $labels[$language] ) !== '' ) {
				return $labels[$language];
			}
		}
		return $key;
	}
}
