<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** Maintenance-only request scope. No configuration switch or public request parameter can enable it. */
final class MigrationWriteScope {
    private static bool $active=false;
    private static array $schemaBases=[];
    private static ?string $schemaRedirect=null;
    public static function active():bool { return self::$active; }
    public static function originalSchema(string $json):?array {
        if (!self::$active) { return null; }
        return self::$schemaBases[ReferenceRewriter::fingerprint($json)] ?? null;
    }
    /** Narrow allowance for one verified retirement write, never ordinary schema editing. */
    public static function allowsSchemaRedirect(string $dbKey):bool {
        return self::$active && self::$schemaRedirect===$dbKey;
    }
    public static function withSchemaRedirect(string $dbKey,\Closure $write):mixed {
        if (!self::$active || self::$schemaRedirect!==null) { throw new \LogicException('Schema redirect requires a non-nested migration write'); }
        self::$schemaRedirect=$dbKey;
        try { return $write(); } finally { self::$schemaRedirect=null; }
    }
    public static function run(\Closure $write,array $schemaBases=[]):mixed {
        if (self::$active) { throw new \LogicException('Nested identity migration'); }
        self::$active=true; self::$schemaBases=$schemaBases;
        try { return $write(); } finally { self::$active=false; self::$schemaBases=[]; self::$schemaRedirect=null; }
    }
}
