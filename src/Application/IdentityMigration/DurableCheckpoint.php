<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

/** The external progress acknowledgement must never precede the actual database commit. */
final readonly class DurableCheckpoint {
    public function __construct(private \Closure $commit,private \Closure $persist) {}
    public function save(array $state):void {
        if(($this->commit)()===false) { throw new \RuntimeException('Database commit/replication acknowledgement failed'); }
        ($this->persist)($state);
    }
}
