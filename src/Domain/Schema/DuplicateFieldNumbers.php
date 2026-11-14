<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\Schema;

/** Check raw schema JSON before decoding can silently discard a repeated object key. */
final class DuplicateFieldNumbers {
    /** @return list<string> */
    public static function find( string $json ): array {
        try { json_decode( $json, false, 512, JSON_THROW_ON_ERROR ); }
        catch ( \JsonException ) { return []; } // Syntax errors belong to the core JSON validator.
        preg_match_all( '/"(?:\\\\.|[^"\\\\])*"|[{}\[\]:,]|-?(?:0|[1-9][0-9]*)(?:\.[0-9]+)?(?:[eE][+-]?[0-9]+)?|true|false|null/s', $json, $matches );
        $tokens = $matches[0]; $i = 0; $seen = []; $duplicates = [];
        $visit = static function ( array $path ) use ( &$visit, &$i, $tokens, &$seen, &$duplicates ): void {
            $token = $tokens[$i++];
            if ( $token === '{' ) {
                while ( $tokens[$i] !== '}' ) {
                    $key = json_decode( $tokens[$i++], true, 512, JSON_THROW_ON_ERROR ); $i++; // colon
                    if ( in_array( $path, [ [ 'propertyDefinitions' ], [ 'schema', 'propertyDefinitions' ] ], true ) ) {
                        $scope = json_encode( $path );
                        if ( isset( $seen[$scope][$key] ) ) { $duplicates[$key] = true; }
                        $seen[$scope][$key] = true;
                    }
                    $visit( [ ...$path, $key ] );
                    if ( $tokens[$i] === ',' ) { $i++; } else { break; }
                }
                $i++; // closing brace
            } elseif ( $token === '[' ) {
                $index = 0;
                while ( $tokens[$i] !== ']' ) {
                    $visit( [ ...$path, $index++ ] );
                    if ( $tokens[$i] === ',' ) { $i++; } else { break; }
                }
                $i++; // closing bracket
            }
        };
        $visit( [] );
        return array_keys( $duplicates );
    }
}
