<?php
declare(strict_types=1);
require __DIR__.'/source-refresh-processing-contract.php';
use D2Harness\State as S;
use MediaWiki\Revision\{RevisionLookup,RevisionRecord};
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationWriteScope,ReferenceRewriter};
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId,LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName};
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\{InternalPageProcessor,SourceRefreshReceipt};
use ProfessionalWiki\SWS\SWSExtension;
$nMigrationSource=0;function migratedSourceCheck(bool $ok,string $why):void{global$nMigrationSource;$nMigrationSource++;if(!$ok)throw new RuntimeException($why);}
resetD2(true);$processor=new InternalPageProcessor();$title=new \MediaWiki\Title\Title();$processor->refresh($title);
$before=S::$page->revision;$oldSchema=S::$schemaRevision;$old=S::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
$oldSnapshot=$old->getSourceSnapshot()->toJson();$legacy=$old->id->text;
$schemaClone=new RevisionRecord(150,902,['main'=>new SchemaContent('{"formatVersion":2,"id":"S2","identityLanguage":"de","labels":{"de":"Person"},"descriptions":{},"propertyDefinitions":{}}')]);
S::$history[150]=$schemaClone;
RevisionLookup::$byTitle=static fn($t)=>$t->getNamespace()===7480?($t->getText()==='S2'?$schemaClone:$oldSchema):S::$page->revision;
$rewriter=new ReferenceRewriter(['Person'=>'S2'],[$legacy=>'E1'],'aewi','de',static fn($name)=>$name);
$next=new SubjectContent($rewriter->subjects($before->getContent('sws')->getText(),'Kurt Gödel'));
$deps=[['namespace'=>7480,'title'=>'S2','revision'=>150]];
try{SourceRefreshReceipt::attachMigrated($title,$before,$next,$deps);throw new LogicException('Unscoped attestation');}catch(\ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete){migratedSourceCheck(true,'Migration source attestation is unavailable outside maintenance scope');}
$next=MigrationWriteScope::run(fn()=>SourceRefreshReceipt::attachMigrated($title,$before,$next,$deps));
S::$page->revision=new RevisionRecord(103,943,['main'=>$before->getContent('main'),'sws'=>$next],$before->getId());S::$history[103]=S::$page->revision;
SWSExtension::$schema=new Schema(new SchemaName('S2'),'',SWSExtension::$schema->getAllProperties(),false,new SchemaId('S2'),new LocalizedIdentity('de',['de'=>'Person'],[]));
$count=count(S::$collected);$processor->refresh($title);
migratedSourceCheck(count(S::$collected)===$count,'Migrated current data processes without recollecting a valid stored source basis');
$after=S::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject()->getSourceSnapshot()->toJson();$expected=$oldSnapshot;$expected['subjectId']='E1';
migratedSourceCheck($after===$expected,'Rebuild preserves source values, attempt times and original provenance');
migratedSourceCheck(S::$plans!==[],'Rebuild still publishes the complete SMW plan');
$processor->refresh($title,null,true);
migratedSourceCheck(count(S::$collected)===$count+1,'Explicit source refresh collects even with a valid source receipt');
S::$mode='cache';$seen=MigrationWriteScope::run(fn()=>\ProfessionalWiki\SWS\Application\Source\SourceDataMode::current());
migratedSourceCheck($seen==='store'&&\ProfessionalWiki\SWS\Application\Source\SourceDataMode::current()==='cache','Migration-local read mode cannot leak into later cache processing');
echo "$nMigrationSource D6 migrated-source/rebuild/SMW/explicit-refresh checks passed (actual processor with controlled MediaWiki/source APIs).\n";
