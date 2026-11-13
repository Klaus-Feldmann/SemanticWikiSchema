<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\IdentityMigration;

use ProfessionalWiki\SWS\Application\Identity\IdentityRegistry;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind,LegacyIdentity};
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

/** Pure preview. Reading existing assignments is permitted; reserving IDs or writing pages is not. */
final readonly class InventoryPlanner {
    public function __construct(private IdentityRegistry $registry,private \Closure $normalizeName) {}

    public function plan(array $inventory):array {
        $planKey=hash('sha256',ReferenceRewriter::encode($inventory));
        $origin=$inventory['wiki'];$counters=$inventory['counters'];
        foreach(['schema','entity']as$kind) { if(!is_int($counters[$kind]??null)||$counters[$kind]<0) { throw new \RuntimeException('Invalid identity counter in inventory'); } }
        if(!preg_match('/^[a-f0-9]{64}\z/',$inventory['owner'])) { throw new \RuntimeException('Invalid migration operator scope'); }
        $occupied=array_fill_keys($inventory['occupiedSchemaTitles'],true);
        $maps=['schemas'=>[],'entities'=>[]];$reservations=[];$hosts=[];
        $allocate=function(IdentityKind $kind,string $legacy)use(&$counters,&$reservations,$occupied,$origin,$planKey):string {
            $existing=$this->registry->findLegacy(new LegacyIdentity($kind,$origin,$legacy));
            if ($existing!==null) { return $existing->text; }
            for($attempt=0;$attempt<100000;$attempt++) {
                $number=++$counters[$kind->value];$id=$kind->fromNumber($number)->text;
                $skip=$kind===IdentityKind::Schema&&isset($occupied[$id]);
                $reservations[]=['kind'=>$kind->value,'legacy'=>$legacy,'id'=>$id,'skip'=>$skip,
                    'request'=>'identity-migration:'.hash('sha256',$planKey."\0".$origin."\0".$kind->value."\0".$legacy).':'.$attempt];
                if (!$skip) { return $id; }
            }
            throw new \RuntimeException('No available ID within bounded migration allocation');
        };
        $seenPages=[];foreach($inventory['records']as$record) {
            if(isset($seenPages[$record['pageId']])) { throw new \RuntimeException('Multiple migration slots on one page require a joint revision plan; no writes performed'); }
            $seenPages[$record['pageId']]=true;
        }
        $records=$inventory['records'];usort($records,static fn($a,$b)=>$a['pageId']<=>$b['pageId']);
        foreach($records as$record) {
            if($record['kind']!=='schema') { continue; }
            $data=ReferenceRewriter::object($record['json']);$key=($this->normalizeName)($record['name']);
            if(isset($maps['schemas'][$key])) { throw new \RuntimeException('Duplicate normalized schema name'); }
            $maps['schemas'][$key]=($data->formatVersion??1)===2?$data->id:$allocate(IdentityKind::Schema,$key);
        }
        foreach($records as$record) {
            if($record['kind']!=='subjects') { continue; }
            $data=ReferenceRewriter::object($record['json']);
            foreach($data->subjects??[]as$id=>$subject) {
                $parsed=new SubjectId($id);
                if(!$parsed->isLocal()) { throw new \RuntimeException('Foreign identity stored as a local entity'); }
                if(isset($hosts[$id])&&$hosts[$id]!==$record['pageId']) { throw new \RuntimeException('Entity has multiple hosting pages: '.$id); }
                $hosts[$id]=$record['pageId'];
                $maps['entities'][$id]=$parsed->isSequential()?$id:$allocate(IdentityKind::Entity,$id);
            }
        }
        $rewrite=new ReferenceRewriter($maps['schemas'],$maps['entities'],$origin,$inventory['language'],$this->normalizeName);
        $operations=[];$retire=[];
        foreach($records as$record) {
            $kind=$record['kind'];$json=$record['json'];
            if($kind==='schema') {
                $id=$maps['schemas'][($this->normalizeName)($record['name'])];
                $next=$rewrite->schema($json,$record['name'],$id);
                if((ReferenceRewriter::object($json)->formatVersion??1)!==2) {
                    $operations[]=['action'=>'create-schema','id'=>$id,'json'=>$next,'source'=>$record];
                    $retire[]=['action'=>'retire-schema','record'=>$record,'json'=>'#REDIRECT [[Schema:'.$id.']]'];
                    continue;
                }
            } elseif($kind==='subjects') { $next=$rewrite->subjects($json,$record['name']); }
            elseif($kind==='mapping') { $next=$rewrite->mapping($json); }
            elseif($kind==='layout') { $next=$rewrite->layout($json); }
            else { throw new \RuntimeException('Unsupported migration record'); }
            $before=ReferenceRewriter::object($json);$after=ReferenceRewriter::object($next);
            unset($before->sourceRefresh,$after->sourceRefresh);
            if(!ReferenceRewriter::equivalent(ReferenceRewriter::encode($before),ReferenceRewriter::encode($after))) {
                $operations[]=['action'=>'update','record'=>$record,'json'=>$next];
            }
        }
        return ['version'=>1,'wiki'=>$origin,'language'=>$inventory['language'],'owner'=>$inventory['owner'],
            'inventoryHash'=>hash('sha256',ReferenceRewriter::encode($inventory)),
            'inventory'=>$inventory,'schemas'=>$maps['schemas'],'entities'=>$maps['entities'],
            'reservations'=>$reservations,'operations'=>array_merge(array_values(array_filter($operations,static fn($op)=>$op['action']==='create-schema')),array_values(array_filter($operations,static fn($op)=>$op['action']!=='create-schema')),$retire)];
    }
}
