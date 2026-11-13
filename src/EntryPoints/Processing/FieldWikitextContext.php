<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\EntryPoints\Processing;

/** A field parse must not append its host's schema programs recursively. */
final class FieldWikitextContext {
    private static int $depth = 0;
    public static function isActive(): bool { return self::$depth > 0; }
    public static function run( callable $callback ): mixed {
        self::$depth++;
        try { return $callback(); } finally { self::$depth--; }
    }
}
