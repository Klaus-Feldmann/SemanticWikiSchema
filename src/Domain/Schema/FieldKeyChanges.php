<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema;

use InvalidArgumentException;

/** Ordered schema edits, not aliases: a saved entity applies each simultaneous map once. */
final class FieldKeyChanges {
    public static function version( mixed $version ): int {
        if ( !is_int( $version ) || $version < 0 ) { throw new InvalidArgumentException( 'Invalid field-key version' ); }
        return $version;
    }

    public static function validateHistory( mixed $history ): array {
        if ( !is_array( $history ) || !array_is_list( $history ) ) { throw new InvalidArgumentException( 'Invalid field-key change history' ); }
        foreach ( $history as $map ) {
            if ( !is_array( $map ) || $map === [] ) { throw new InvalidArgumentException( 'Empty field-key change' ); }
            $targets = [];
            foreach ( $map as $old => $new ) {
                if ( !is_string( $old ) || !is_string( $new ) || $old === $new ) { throw new InvalidArgumentException( 'Invalid field-key change' ); }
                PropertyNameRules::assertValid( $old ); PropertyNameRules::assertValid( $new );
                if ( isset( $targets[$new] ) ) { throw new InvalidArgumentException( 'Field-key targets must be unique' ); }
                $targets[$new] = true;
            }
        }
        return $history;
    }

    public static function validateEdit( array $old, array $new ): void {
        $before = self::validateHistory( $old['fieldKeyChanges'] ?? [] );
        $after = self::validateHistory( $new['fieldKeyChanges'] ?? [] );
        if ( array_slice( $after, 0, count( $before ) ) != $before || count( $after ) > count( $before ) + 1 ) {
            throw new InvalidArgumentException( 'Field-key history is append-only; append one simultaneous change per schema save' );
        }
        if ( count( $after ) === count( $before ) ) { return; }
        $map = $after[array_key_last( $after )];
        foreach ( $map as $from => $to ) {
            $prior = $old['propertyDefinitions'][$from] ?? null; $next = $new['propertyDefinitions'][$to] ?? null;
            if ( $prior === null || $next === null || ( $prior['type'] ?? null ) !== ( $next['type'] ?? null ) ) {
                throw new InvalidArgumentException( 'A key transfer requires the original and target field with the same datatype' );
            }
            if ( isset( $old['propertyDefinitions'][$to] ) && !array_key_exists( $to, $map ) ) {
                throw new InvalidArgumentException( 'Cannot transfer into another existing field' );
            }
            if ( isset( $new['propertyDefinitions'][$from] ) && !in_array( $from, $map, true ) ) {
                throw new InvalidArgumentException( 'The previous field key is still in use' );
            }
        }
    }

    /** Transform raw JSON data so unknown fields, relation IDs and source provenance survive. */
    public static function transfer( array $entity, array $history ): array {
        $history = self::validateHistory( $history );
        $version = self::version( $entity['fieldKeyVersion'] ?? 0 );
        if ( $version > count( $history ) ) { throw new InvalidArgumentException( 'Entity field-key version is newer than its schema' ); }
        foreach ( array_slice( $history, $version ) as $map ) {
            foreach ( [ 'statements', 'fieldNotes' ] as $part ) {
                if ( isset( $entity[$part] ) ) { $entity[$part] = self::rekey( (array)$entity[$part], $map ); }
            }
            if ( isset( $entity['sourceSnapshot']['languages'] ) ) {
                foreach ( $entity['sourceSnapshot']['languages'] as &$language ) {
                    $language['fields'] = self::rekey( $language['fields'], $map );
                    foreach ( $language['fields'] as $key => &$entry ) {
                        if ( isset( $entry['lastAttempt']['name'] ) ) { $entry['lastAttempt']['name'] = (string)$key; }
                        if ( isset( $entry['lastSuccess']['result']['name'] ) ) { $entry['lastSuccess']['result']['name'] = (string)$key; }
                    }
                    unset( $entry );
                }
                unset( $language );
            }
        }
        if ( $history !== [] ) { $entity['fieldKeyVersion'] = count( $history ); }
        return $entity;
    }

    private static function rekey( array $data, array $map ): array {
        $next = [];
        foreach ( $data as $key => $value ) {
            $target = $map[$key] ?? $key;
            if ( array_key_exists( $target, $next ) ) { throw new InvalidArgumentException( 'Field-key transfer would overwrite data: ' . $target ); }
            $next[$target] = $value;
        }
        return $next;
    }
}
