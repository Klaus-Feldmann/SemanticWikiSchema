<?php

declare(strict_types=1);
require __DIR__.'/source-refresh-processing-contract.php';

use D2Harness\State as R;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId,LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,SubjectMap};
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use MediaWiki\Revision\RevisionRecord;

$extra=0;
function identityRefreshCheck(bool $ok,string $why):void{global$extra;$extra++;if(!$ok)throw new RuntimeException($why);}
resetD2(true);
$old=R::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
SWSExtension::$schema=new Schema(new SchemaName('S1'),'',SWSExtension::$schema->getAllProperties(),false,new SchemaId('S1'),new LocalizedIdentity('de',['de'=>'Person','en'=>'Person']));
$identity=new LocalizedIdentity('de',['de'=>'Kurt Gödel','en'=>'Kurt Goedel'],['de'=>'Mathematiker','en'=>'Logician']);
$entity=new Subject(new SubjectId('E1'),null,SWSExtension::$schema->getReference(),$old->getStatements(),$old->getFieldNotes(),identity:$identity);
R::$page->revision=new RevisionRecord(101,943,['main'=>R::$page->revision->getContent('main'),'sws'=>SubjectContent::newFromData(new PageSubjects($entity,new SubjectMap()))]);
R::$history[101]=R::$page->revision;
$processor->refresh($title);
$stored=R::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
identityRefreshCheck($stored?->id->text==='E1'&&$stored->getSchemaReference()->toJson()===['id'=>'S1'],'Worker preserves new entity and schema identities');
identityRefreshCheck($stored?->getIdentity()==$identity,'Internal source save preserves multilingual labels and descriptions');
identityRefreshCheck($stored?->getFieldNotes()===$old->getFieldNotes(),'Source processing preserves research notes');
identityRefreshCheck($stored?->getSourceSnapshot()?->getSubjectId()==='E1','New E entity receives its own local source snapshot');
identityRefreshCheck(R::$page->saveCalls===1,'Source snapshot is stored in one additional revision');
identityRefreshCheck(\ProfessionalWiki\SWS\EntryPoints\Processing\SourceRefreshReceipt::isCurrent($title,R::$page->revision),'Receipt covers version-2 content and its metadata');
$before=R::$page->saveCalls;$processor->refresh($title);
identityRefreshCheck(R::$page->saveCalls===$before,'Sealed v2 snapshot does not cause a refresh loop');
R::$mode='cache';editedD2();$processor->refresh($title);
$cached=R::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
identityRefreshCheck($cached?->getIdentity()==$identity&&$cached?->id->text==='E1','Cache-mode processing retains multilingual entity metadata');
print "$extra D2 v2 source-worker/receipt/store/cache checks passed (real worker with controlled MediaWiki/SMW/source host).\n";
