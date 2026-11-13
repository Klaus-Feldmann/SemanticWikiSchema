<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Application\Identity;

/** Only presentation metadata is ignored. Unknown and future fields remain substantive. */
final class IdentityChangeClassifier {
    public static function schema( string $before, string $after ): string {
        return self::classify( $before, $after, false );
    }
    public static function subjects( string $before, string $after ): string {
        return self::classify( $before, $after, true );
    }
    private static function classify( string $before, string $after, bool $subjects ): string {
        try {
            $a = json_decode( $before, true, 512, JSON_THROW_ON_ERROR );
            $b = json_decode( $after, true, 512, JSON_THROW_ON_ERROR );
            if ( !is_array( $a ) || !is_array( $b ) ) { return 'content'; }
            unset( $a['sourceRefresh'], $b['sourceRefresh'] );
            if ( self::canonical( $a ) === self::canonical( $b ) ) { return 'unchanged'; }
            foreach ( [ &$a, &$b ] as &$document ) {
                if ( $subjects ) {
                    if ( !is_array( $document['subjects'] ?? null ) ) { return 'content'; }
                    foreach ( $document['subjects'] as &$subject ) {
                        if ( !is_array( $subject ) ) { return 'content'; }
                        self::stripMetadata( $subject, false );
                    }
                    unset( $subject );
                } else { self::stripMetadata( $document, true ); }
            }
            unset( $document );
            return self::canonical( $a ) === self::canonical( $b ) ? 'metadata' : 'content';
        } catch ( \JsonException ) { return 'content'; }
    }
    private static function stripMetadata( array &$data, bool $schema ): void {
        foreach ( [ 'label', 'labels', 'descriptions', 'identityLanguage' ] as $key ) { unset( $data[$key] ); }
        if ( $schema ) { unset( $data['description'] ); }
    }
    private static function canonical( mixed $value ): mixed {
        if ( !is_array( $value ) ) { return $value; }
        if ( !array_is_list( $value ) ) { ksort( $value ); }
        return array_map( self::canonical(...), $value );
    }
}
