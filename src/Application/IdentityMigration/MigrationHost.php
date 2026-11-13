<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

interface MigrationHost {
    /** Read-only, revision-bound records: schemas, subjects, mappings, layouts, occupied schema titles, counters. */
    public function inventory():array;
    /** Refuse intervening edits, unexpected records and nonmatching resumed results before further writes. */
    public function preflight(array $plan):void;
    /** Create-only or verified identical prior result; no arbitrary replacement. */
    public function createSchema(string $id,string $json):void;
    /** CAS: verify unchanged base or exact already-written result after a lost acknowledgement. */
    public function updateRecord(array $record,string $json,bool $redirect=false):void;
    /** Rebuild derived indices/projections from published revisions; throw rather than hiding a failure. */
    public function rebuild():void;
}
