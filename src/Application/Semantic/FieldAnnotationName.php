<?php

declare( strict_types = 1 );
namespace AEWI\SWS\Application\Semantic;

use InvalidArgumentException;
use AEWI\SWS\Domain\Schema\FieldLabels;
use AEWI\SWS\Presentation\FieldLabelLanguages;

/** SMW names follow content-language field labels; explicit mappings retain priority. */
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
		$definition = [];
		foreach ( $schema['properties'] ?? [] as $property ) {
			if ( ( $property['name'] ?? null ) === $field ) { $definition = $property; break; }
		}
		$languages = $result['annotationLanguages'] ?? FieldLabelLanguages::forReader(
			(string)( $GLOBALS['wgLanguageCode'] ?? 'en' )
		);
		$name = trim( FieldLabels::display( $definition['labels'] ?? [], $field, $languages ) );
		// This is an actual SMW property name, not an encoded internal identity.
		// Reject names that cannot be passed to #set rather than silently changing them.
		SmwMappingConfig::resolve( [ 'properties' => [ 'field' => [ 'name' => $name ] ] ] );
		return $name;
	}
}
