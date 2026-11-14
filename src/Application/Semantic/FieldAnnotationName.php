<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Semantic;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Schema\FieldNameResolver;

/** Stable SMW names: explicit mappings win; all keys default to schema-qualified names. */
final class FieldAnnotationName {
	public static function resolve( array $result, string $field, array $config ): string {
		$schema = $result['schema'];
		$scope = (string)( $schema['id'] ?? $schema['name'] );
		$reference = $result['subject']['schema'] ?? null;
		if ( is_array( $reference ) && isset( $reference['source'] ) ) { $scope = $reference['source'] . ':' . $scope; }
		$names = $config['properties'][$scope] ?? $config['properties'][$schema['name']] ?? [];
		if ( !is_array( $names ) ) { throw new InvalidArgumentException( 'Invalid schema property mapping' ); }
		$candidate = $field;
		if ( !array_key_exists( $candidate, $names ) ) {
			foreach ( array_reverse( $schema['fieldKeyChanges'] ?? [] ) as $map ) {
				$prior = array_search( $candidate, $map, true );
				if ( $prior !== false ) { $candidate = $prior; }
				if ( array_key_exists( $candidate, $names ) ) { break; }
			}
		}
		if ( array_key_exists( $candidate, $names ) ) {
			if ( !is_string( $names[$candidate] ) ) { throw new InvalidArgumentException( 'Invalid property mapping' ); }
			return trim( $names[$candidate] );
		}
		// #set separates the property and value at '='. Escape it injectively in defaults.
		return $scope . ':' . strtr( $field, [ '%' => '%25', '=' => '%3D' ] );
	}
}
