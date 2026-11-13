<?php
declare(strict_types=1);
// Actual processor, writer, receipts and signature guards with the existing controlled MediaWiki host.
require __DIR__.'/source-refresh-processing-contract.php';
use D2Harness\State as S;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\Application\Identity\IdentityChangeClassifier as C;
use ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\EntryPoints\Content\{SubjectContent,SchemaContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\{SourceRefreshReceipt,InternalPageProcessor,ProcessingResult};
$countD5=0;
function d5check(bool $ok,string $why):void{global$countD5;$countD5++;if(!$ok)throw new RuntimeException($why);}
$a=['formatVersion'=>2,'id'=>'S1','identityLanguage'=>'de','labels'=>['de'=>'Person'],'descriptions'=>['de'=>'Beschreibung'],'propertyDefinitions'=>['Ort'=>['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P19']]]];
$b=$a;$b['labels']['de']='Mensch';$b['descriptions']['en']='Human';
d5check(C::schema(json_encode($a),json_encode($b))==='metadata','Schema labels and descriptions are presentation changes');
foreach(['identityLanguage','id','propertyDefinitions','unknown']as$key){$x=$b;$x[$key]=$key==='identityLanguage'?'en':($key==='propertyDefinitions'?['Neu'=>['type'=>'text']]:'changed');d5check(C::schema(json_encode($b),json_encode($x))===($key==='identityLanguage'?'metadata':'content'),"Classifier preserves substantive $key");}
d5check(C::schema('broken',json_encode($a))==='content','Invalid document never gets fast path');
d5check(C::schema(json_encode($a),json_encode(array_reverse($a,true)))==='unchanged','Object-key order is irrelevant');
resetD2(true);$base=S::$page->revision;$initial=$base->getContent('sws')->getPageSubjects();
$initial->getMainSubject()->setIdentity(new LocalizedIdentity('de',['de'=>'Vorher'],[]));
S::$page->revision=new RevisionRecord(101,943,['main'=>$base->getContent('main'),'sws'=>SubjectContent::newFromData($initial)]);S::$history[101]=S::$page->revision;
$processor=new InternalPageProcessor();$title=new \MediaWiki\Title\Title();$processor->refresh($title);
$parent=S::$page->revision;
$subjects=$parent->getContent('sws')->getPageSubjects();
$subjects->getMainSubject()->setIdentity(new LocalizedIdentity('de',['de'=>'Umbenannt'],['de'=>'Eigene Beschreibung']));
$subjects->getMainSubject()->setLabel(new SubjectLabel('Umbenannt'));
$next=SubjectContent::newFromData($subjects);
$carried=SourceRefreshReceipt::carryMetadata($title,$parent,$next);
d5check(str_contains($carried->getText(),'sourceRefresh'),'Pure label edit carries signed source basis');
$revision=new RevisionRecord($parent->getId()+1,943,['main'=>$parent->getContent('main'),'sws'=>$carried],$parent->getId());
S::$page->revision=$revision;S::$history[$revision->getId()]=$revision;
d5check(SourceRefreshReceipt::isCurrent($title,$revision),'Carried receipt verifies against exact new content and parent');
$updates=[new \MediaWiki\Deferred\LinksUpdate\LinksUpdate()];
$rendered=new class($revision){public function __construct(private $revision){}public function getRevision(){return $this->revision;}public function getRevisionParserOutput(){return new \MediaWiki\Parser\ParserOutput();}};
\ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingHooks::onRevisionDataUpdates($title,$rendered,$updates);
d5check($updates===[]&&count(S::$queued)>0,'Metadata source receipt cannot bypass the fresh complete-processing job');
S::$queued=[];

$collected=count(S::$collected);$saves=S::$page->saveCalls;$processor->refresh($title);
d5check(count(S::$collected)===$collected&&S::$page->saveCalls===$saves,'Metadata-only refresh makes no source request or source-only revision');
d5check(ProcessingResult::isCurrent($title,end(S::$published)['known-revision-output']->getExtensionData(ProcessingResult::STAMP)),'Metadata output receives a fresh exact-revision publication signature');
d5check(S::$plans!==[],'SMW projection remains in the complete sealed processing path');
// Subsequent source-schema label changes are reusable; full output stamps still become stale.
$oldSchema=S::$schemaRevision;$schemaData=json_decode($oldSchema->getContent('main')->getText(),true);$schemaData['labels']=['de'=>'Personen'];
$newSchema=new RevisionRecord(150,901,['main'=>new SchemaContent(json_encode($schemaData))],$oldSchema->getId());
S::$schemaRevision=$newSchema;S::$history[150]=$newSchema;
d5check(SourceRefreshReceipt::isCurrent($title,S::$page->revision),'Schema-only metadata change reuses the signed source basis');
$processor->refresh($title);
d5check(count(S::$collected)===$collected,'Schema label edit does not recollect external data');
$signed=end(S::$published)['known-revision-output']->getExtensionData(ProcessingResult::STAMP);
$oldStamp=$signed; $oldStamp['dependencies'][0]['revision']=$oldSchema->getId();
$oldStamp=ProcessingResult::seal(array_diff_key($oldStamp,['signature'=>true]));
d5check(!ProcessingResult::isCurrent($title,$oldStamp),'Source-reuse tolerance never relaxes exact dependency checks for publication stamps');
S::$schemaRevision->readable=false;
d5check(!SourceRefreshReceipt::isCurrent($title,S::$page->revision),'Unreadable changed schema cannot authorize source reuse');
S::$schemaRevision->readable=true;
// Unknown or source-affecting schema data must not be ignored.
$schemaData['propertyDefinitions']['Quelle']=['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P20']];
S::$schemaRevision=new RevisionRecord(151,901,['main'=>new SchemaContent(json_encode($schemaData))],150);S::$history[151]=S::$schemaRevision;
d5check(!SourceRefreshReceipt::isCurrent($title,S::$page->revision),'Source binding change invalidates source reuse');
$processor->refresh($title);d5check(count(S::$collected)>$collected,'Substantive schema edit follows full source collection');
// Copied/modified receipts cannot authorize unrelated state.
$parent=S::$page->revision;$raw=json_decode($parent->getContent('sws')->getText(),true);$id=array_key_first($raw['subjects']);$raw['subjects'][$id]['fieldNotes']['Quelle']='Manipuliert';
$bad=new RevisionRecord(170,943,['main'=>$parent->getContent('main'),'sws'=>new SubjectContent(json_encode($raw))],$parent->getParentId());
d5check(!SourceRefreshReceipt::isCurrent($title,$bad),'Receipt cannot be copied onto changed field notes');
S::$mode='cache';$subjects=$parent->getContent('sws')->getPageSubjects();$subjects->getMainSubject()->setLabel(new SubjectLabel('Cache-Name'));
$next=SubjectContent::newFromData($subjects);
d5check(SourceRefreshReceipt::carryMetadata($title,$parent,$next)->getText()===$next->getText(),'Cache mode is not silently changed into persistent-store reuse');
// Exercise the ordinary repository/CAS save path, not just the receipt helper.
resetD2(true);$base=S::$page->revision;$initial=$base->getContent('sws')->getPageSubjects();
$initial->getMainSubject()->setIdentity(new LocalizedIdentity('de',['de'=>'Vorher'],[]));
S::$page->revision=new RevisionRecord(101,943,['main'=>$base->getContent('main'),'sws'=>SubjectContent::newFromData($initial)]);S::$history[101]=S::$page->revision;
$processor->refresh($title);$subjects=S::$page->revision->getContent('sws')->getPageSubjects();$subjects->getMainSubject()->setLabel(new SubjectLabel('Repository-Name'));
$actor=new class implements \MediaWiki\Permissions\Authority {public function authorizeRead(string $action,mixed $title):bool{return true;}public function authorizeWrite(string $action,mixed $title):bool{return true;}};
$repository=new \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository(
 new \ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup(),new \MediaWiki\Revision\RevisionLookup(S::$page),
 new \ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSaver(new \MediaWiki\Page\WikiPageFactory(S::$page),$actor),
 new \MediaWiki\Title\TitleFactory(),new class implements \MediaWiki\Content\IContentHandlerFactory {},
 new \ProfessionalWiki\SWS\Application\DependentSubjectGuard(\ProfessionalWiki\SWS\Tests\Data\TestNaming::newDependentSubjectRules(),static fn()=>new \ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup())
);
$status=$repository->savePageSubjects($subjects,new \ProfessionalWiki\SWS\Domain\Page\PageId(943));
d5check($status->status===\ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus::REVISION_CREATED&&SourceRefreshReceipt::isCurrent($title,S::$page->revision),'Actual CAS repository save carries the verified source basis');
$fetches=count(S::$collected);$processor->refresh($title);
d5check(count(S::$collected)===$fetches,'Actual repository label edit reaches full processing without fresh source collection');
echo "$countD5 D5 metadata/source-reuse/signature/cache-boundary checks passed (actual processor with controlled MW host).\n";
