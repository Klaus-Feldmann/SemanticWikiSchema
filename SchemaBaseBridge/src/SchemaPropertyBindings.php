<?php

declare( strict_types = 1 );

namespace SWSBaseBridge;

use Closure;
use MediaWiki\Context\IContextSource;
use RuntimeException;

/** Validates only new or changed external property bindings before a schema is saved. */
final class SchemaPropertyBindings {
	/**
	 * The optional lookup closure makes the compatibility rule testable without a live endpoint.
	 * Production schema saves pass a context and use the configured source gateway.
	 */
	public static function validate(
		array $schema,
		array $previous,
		?IContextSource $context = null,
		?Closure $entityLookup = null
	): void {
		$groups = [];
		foreach ( $schema['propertyDefinitions'] ?? [] as $name => $definition ) {
			$binding = $definition['externalProperty'] ?? null;
			if ( $binding === null ) {
				continue;
			}
			$oldName = $name;
			$before = count( $previous['fieldKeyChanges'] ?? [] );
			$history = $schema['fieldKeyChanges'] ?? [];
			if ( count( $history ) === $before + 1 ) { $oldName = array_search( $name, $history[$before], true ) ?: $name; }
			$old = $previous['propertyDefinitions'][$oldName] ?? [];
			if ( ( $old['externalProperty'] ?? null ) === $binding && ( $old['type'] ?? null ) === $definition['type'] ) {
				continue;
			}
			$groups[$binding['source']][$name] = $definition;
		}

		if ( $groups !== [] && $context === null && $entityLookup === null ) {
			throw new RuntimeException( 'Property source context unavailable' );
		}
		foreach ( $groups as $source => $fields ) {
			$ids = array_values( array_unique( array_map(
				static fn ( array $definition ): string => $definition['externalProperty']['id'],
				$fields
			) ) );
			$language = $context?->getLanguage()->getCode() ?? 'en';
			if ( $entityLookup !== null ) {
				$entities = $entityLookup( $source, $ids, $language, false );
			} else {
				if ( $context === null ) {
					throw new RuntimeException( 'Property source context unavailable' );
				}
				$entities = ( new EntityGateway( $context ) )->entities( $source, $ids, $language, false );
			}

			foreach ( $fields as $name => $definition ) {
				$id = $definition['externalProperty']['id'];
				$datatype = $entities[$id]['datatype'] ?? null;
				if ( !is_string( $datatype ) ) {
					throw new RuntimeException( $name . ' / ' . $id . ': datatype lookup unavailable' );
				}
				if ( !PropertyDatatypePolicy::supports( $definition['type'], $datatype ) ) {
					throw new RuntimeException( $name . ' / ' . $id . ': ' . $datatype . ' → ' . $definition['type'] );
				}
			}
		}
	}
}
