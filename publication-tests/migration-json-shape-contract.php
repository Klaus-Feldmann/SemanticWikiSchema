<?php
declare(strict_types=1);
require __DIR__.'/migration-source-basis-contract.php';

use D2Harness\State as ShapeState;
use MediaWiki\Revision\{RevisionLookup,RevisionRecord};
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationJsonShapeRepair,MigrationWriteScope,ReferenceRewriter};
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\{InternalPageProcessor,SourceRefreshReceipt};
use ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration\MediaWikiMigrationHost;

$shapeChecks=0;
function shapeCheck(bool $ok,string $why):void{global $shapeChecks;$shapeChecks++;if(!$ok)throw new RuntimeException($why);}
function shapeRefuses(callable $action,string $why):void{try{$action();}catch(RuntimeException){shapeCheck(true,$why);return;}throw new RuntimeException('Accepted: '.$why);}
shapeCheck(MigrationJsonShapeRepair::matches('{"subjects":{"E1":{"descriptions":{}}}}','{"subjects":{"E1":{"descriptions":[]}}}'),'Exact reported empty-object conversion recognized');
shapeCheck(!MigrationJsonShapeRepair::matches('{"x":{}}','{"x":{}}'),'Already correct objects need no repair');
shapeCheck(!MigrationJsonShapeRepair::matches('{"x":{},"note":"research"}','{"x":[],"note":"changed"}'),'Research changes are never normalized away');
shapeCheck(!MigrationJsonShapeRepair::matches('{"x":{"de":"A"}}','{"x":[]}'),'Populated descriptions cannot be erased');
shapeCheck(!MigrationJsonShapeRepair::matches('{"x":[]}','{"x":{}}'),'Inverse array-to-object changes not accepted');

resetD2(true);(new InternalPageProcessor())->refresh(new MediaWiki\Title\Title());
$original=ShapeState::$page->revision;
$schemaClone=new RevisionRecord(150,902,['main'=>new SchemaContent('{"formatVersion":2,"id":"S2","identityLanguage":"de","labels":{"de":"Person"},"descriptions":{},"propertyDefinitions":{}}')]);
ShapeState::$history[150]=$schemaClone;
$oldSchema=ShapeState::$schemaRevision;
RevisionLookup::$byTitle=static fn($t)=>$t->getNamespace()===7480?($t->getText()==='S2'?$schemaClone:$oldSchema):ShapeState::$page->revision;
$legacy=$original->getContent('sws')->getPageSubjects()->getMainSubject()->id->text;
$rewriter=new ReferenceRewriter(['Person'=>'S2'],[$legacy=>'E1'],'aewi','de',static fn($s)=>$s);
$expected=$rewriter->subjects($original->getContent('sws')->getText(),'Kurt Gödel');
$title=new MediaWiki\Title\Title();$deps=[['namespace'=>7480,'title'=>'S2','revision'=>150]];
$next=MigrationWriteScope::run(fn()=>SourceRefreshReceipt::attachMigrated($title,$original,new SubjectContent($expected),$deps));
$payload=ReferenceRewriter::object($next->getText());unset($payload->sourceRefresh);
shapeCheck(ReferenceRewriter::equivalent(ReferenceRewriter::encode($payload),$expected),'New migration attestation preserves exact object/list shapes');
$broken=new SubjectContent(json_encode(json_decode($next->getText(),true,512,JSON_THROW_ON_ERROR),JSON_THROW_ON_ERROR));
$migration=new RevisionRecord(103,943,['main'=>$original->getContent('main'),'sws'=>$broken],$original->getId());
ShapeState::$page->revision=$migration;ShapeState::$history[103]=$migration;
shapeCheck(SourceRefreshReceipt::isCurrent($title,$migration),'Legacy object loss reproduces with a valid signed receipt');
shapeCheck(MigrationJsonShapeRepair::matches($expected,$broken->getText()),'Actual legacy migration output matches only the known container loss');
$hashSlot=static fn($c)=>hash('sha256',$c->getModel()."\0".$c->serialize());
$before=['pageId'=>943,'revisionId'=>$original->getId(),'revisionSha1'=>$original->getSha1(),'slot'=>'sws','slots'=>['main'=>$hashSlot($original->getContent('main')),'sws'=>$hashSlot($original->getContent('sws'))],'kind'=>'subjects','pageName'=>'Kurt Gödel'];
$current=array_replace($before,['revisionId'=>103,'revisionSha1'=>$migration->getSha1()]);
$actor=new class implements MediaWiki\Permissions\Authority {public function authorizeRead(string $a,mixed $t):bool{return true;}public function authorizeWrite(string $a,mixed $t):bool{return true;}};
$host=new MediaWikiMigrationHost($actor,static fn($s)=>$s);
$repair=(new ReflectionClass($host))->getMethod('repairJsonShape');$verify=(new ReflectionClass($host))->getMethod('verifiedShapeRepairRevision');
$requests=count(ShapeState::$collected);$main=$migration->getContent('main');
MigrationWriteScope::run(fn()=>$repair->invoke($host,$before,$current,$expected));
$repaired=ShapeState::$page->revision;$actual=ReferenceRewriter::object($repaired->getContent('sws')->getText());unset($actual->sourceRefresh);
shapeCheck(ReferenceRewriter::equivalent(ReferenceRewriter::encode($actual),$expected),'Actual repair restores exact signed-plan payload');
shapeCheck($repaired->getContent('main')===$main&&array_keys(ShapeState::$page->lastWrittenSlots)===['sws'],'Repair changes only SWS slot');
shapeCheck(SourceRefreshReceipt::isCurrent($title,$repaired),'New receipt binds repair parent and remains valid');
shapeCheck(count(ShapeState::$collected)===$requests,'Repair performs no new source collection');
shapeCheck(!MigrationJsonShapeRepair::matches($expected,$repaired->getContent('sws')->getText()),'Repaired record needs no second repair');
shapeCheck($actual->subjects->E1->sourceSnapshot==ReferenceRewriter::object($expected)->subjects->E1->sourceSnapshot,'Source values, statuses, timestamps and provenance preserved');

// A changed receipt or unrelated slot is refused before write.
ShapeState::$page->revision=$migration;
$bad=ReferenceRewriter::object($broken->getText());$bad->sourceRefresh->signature='invalid';
ShapeState::$page->revision=new RevisionRecord(103,943,['main'=>$main,'sws'=>new SubjectContent(ReferenceRewriter::encode($bad))],$original->getId());
$badCurrent=array_replace($current,['revisionSha1'=>ShapeState::$page->revision->getSha1()]);
shapeRefuses(fn()=>$verify->invoke($host,$before,$badCurrent,$expected),'Forged source receipt rejected');
ShapeState::$page->revision=new RevisionRecord(103,943,['main'=>new MediaWiki\Content\JsonContent('changed article'),'sws'=>$broken],$original->getId());
$badCurrent=array_replace($current,['revisionSha1'=>ShapeState::$page->revision->getSha1()]);
shapeRefuses(fn()=>$verify->invoke($host,$before,$badCurrent,$expected),'Unrelated article changes rejected');
ShapeState::$page->revision=new RevisionRecord(103,943,['main'=>$main,'sws'=>$broken],999);
shapeRefuses(fn()=>$verify->invoke($host,$before,$current,$expected),'Non-original parent rejected');
ShapeState::$page->revision=$migration;
ShapeState::$page->beforeSave=static function($page)use($migration):void{$page->revision=new RevisionRecord(900,943,$migration->content,$migration->getId());};
shapeRefuses(fn()=>MigrationWriteScope::run(fn()=>$repair->invoke($host,$before,$current,$expected)),'Concurrent save rejected by actual CAS saver');
shapeCheck(ShapeState::$page->revision->getId()===900&&!MigrationWriteScope::active(),'Concurrent state retained and migration scope closed');
ShapeState::$page->beforeSave=null;
echo "$shapeChecks empty JSON shape/receipt/CAS/source-preservation checks passed with actual receipt, migration host and saver; controlled MW boundary.\n";
