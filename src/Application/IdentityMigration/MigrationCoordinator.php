<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

use ProfessionalWiki\SWS\Application\Identity\IdentityRegistry;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind,LegacyIdentity};

/** Stable plan plus idempotent operations. A failed page never advances its checkpoint. */
final readonly class MigrationCoordinator {
    public function __construct(private IdentityRegistry $registry,private MigrationHost $host) {}
    public function apply(array $plan,array $state,\Closure $checkpoint):array {
        if(($plan['version']??null)!==1) { throw new \RuntimeException('Unsupported migration plan'); }
        $hash=hash('sha256',ReferenceRewriter::encode($plan));
        if(($state['planHash']??$hash)!==$hash) { throw new \RuntimeException('Checkpoint belongs to another plan'); }
        $state+=['planHash'=>$hash,'reservation'=>0,'operation'=>0,'rebuilt'=>false];
        if(!is_int($state['reservation'])||!is_int($state['operation'])||$state['reservation']<0||$state['reservation']>count($plan['reservations'])||$state['operation']<0||$state['operation']>count($plan['operations'])) { throw new \RuntimeException('Invalid migration checkpoint cursor'); }
        if($state['rebuilt']) {
            if($state['operation']!==count($plan['operations'])||$state['reservation']!==count($plan['reservations'])) { throw new \RuntimeException('Incomplete migration cannot claim rebuilt data'); }
            return $state;
        }
        $checkpoint($state);
        // Once all identity writes completed, rebuilding is deliberately based on current data.
        // A previous partial rebuild may already have refreshed source values legitimately.
        if($state['operation']<count($plan['operations'])) { $this->host->preflight($plan); }
        foreach($plan['reservations']as$i=>$reservation) {
            if($i<$state['reservation']) { continue; }
            $kind=IdentityKind::from($reservation['kind']);
            $id=$this->registry->reserveForOwner($kind,$reservation['request'],$plan['owner']);
            if($id->text!==$reservation['id']) { throw new \RuntimeException('ID counter changed since preview; migration stopped before page replacement'); }
            if(!$reservation['skip']) { $this->registry->linkLegacy(new LegacyIdentity($kind,$plan['wiki'],$reservation['legacy']),$id); }
            $state['reservation']=$i+1;$checkpoint($state);
        }
        $schemaBases=[];
        foreach($plan['operations']as$op) {
            if($op['action']==='create-schema') { $schemaBases[ReferenceRewriter::fingerprint($op['json'])]=json_decode($op['source']['json'],true,512,JSON_THROW_ON_ERROR); }
        }
        MigrationWriteScope::run(function()use($plan,&$state,$checkpoint):void {
            foreach($plan['operations']as$i=>$operation) {
                if($i<$state['operation']) { continue; }
                if($operation['action']==='create-schema') {
                    $this->host->createSchema($operation['id'],$operation['json']);
                } else {
                    $this->host->updateRecord($operation['record'],$operation['json'],$operation['action']==='retire-schema');
                }
                $state['operation']=$i+1;$checkpoint($state);
            }
        },$schemaBases);
        if(!$state['rebuilt']) { $this->host->rebuild();$state['rebuilt']=true;$checkpoint($state); }
        return $state;
    }
}
