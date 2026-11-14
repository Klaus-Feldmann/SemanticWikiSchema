<?php
require __DIR__.'/source-refresh-processing-contract.php';
use D2Harness\State as K;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalFieldKeyWriter;
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyDefinitions};
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\SWS\SWSExtension;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;

resetD2();
// Populate a real snapshot first; then rename both an own and a derived field.
$processor->refresh($title);
$before=K::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
$source=$before->getSourceSnapshot()->toJson();
$defs=SWSExtension::$schema->getAllProperties()->asMap();
SWSExtension::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['N1'=>$defs['Name'],'Z1'=>$defs['Quelle']]),false,fieldKeyChanges:[['Name'=>'N1','Quelle'=>'Z1']]);
$wire=(new SchemaPresentationSerializer())->serialize(SWSExtension::$schema);
K::$schemaRevision=new RevisionRecord(200,901,['main'=>new SchemaContent($wire)]);K::$history[200]=K::$schemaRevision;
$saveCalls=K::$page->saveCalls;
(new InternalFieldKeyWriter())->apply($title);
$after=K::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject();
d2check($after->getStatements()->getStatement(new \ProfessionalWiki\SWS\Domain\Schema\PropertyName('N1'))->getValue()->toScalars()===['Eigene Angabe'],'Own value moved to arbitrary key');
d2check($after->getFieldNotes()===['Z1'=>'Forschungsnotiz'],'Field note moved with source field');
d2check($after->getFieldKeyVersion()===1,'Applied version persisted');
$stock=$after->getSourceSnapshot()->toJson();
d2check($stock['languages']['de']['fields']['Z1']['lastSuccess']['result']['values']===$source['languages']['de']['fields']['Quelle']['lastSuccess']['result']['values'],'Last source success retained');
d2check($stock['languages']['de']['fields']['Z1']['lastAttempt']['name']==='Z1','Source metadata uses new key');
d2check($stock['languages']['de']['identities']===$source['languages']['de']['identities'],'Source identities unchanged');
d2check(K::$page->lastWrittenSlots['sws'] instanceof \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent && count(K::$page->lastWrittenSlots)===1,'Only subject slot written');
d2check(K::$page->revision->getContent('main')->getText()==='Artikel','Article body untouched');
(new InternalFieldKeyWriter())->apply($title);
d2check(K::$page->saveCalls===$saveCalls+1,'Retry is a no-op, not a second migration');
// Subsequent processing must read the rewritten slot and project the new schema fields.
$processor->refresh($title);
d2check(K::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject()->getFieldKeyVersion()===1,'Ordinary processing preserves applied version');
// Refuse an orphan value that would collide with the target, without writing any slot.
resetD2();
$raw=json_decode(K::$page->revision->getContent('sws')->getText(),true);
$raw['subjects']['s11111111111111']['statements']['N1']=['propertyType'=>'text','value'=>['Existing orphan']];
K::$page->revision=new RevisionRecord(101,943,['main'=>new \MediaWiki\Content\JsonContent('Artikel'),'sws'=>new \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent(json_encode($raw))]);K::$history[101]=K::$page->revision;
K::$schemaRevision=new RevisionRecord(200,901,['main'=>new SchemaContent($wire)]);K::$history[200]=K::$schemaRevision;
$beforeText=K::$page->revision->getContent('sws')->getText();
d2refuses(fn()=>(new InternalFieldKeyWriter())->apply($title),'Collision is refused before writing');
d2check(K::$page->saveCalls===0 && K::$page->revision->getContent('sws')->getText()===$beforeText,'Collision retains original slot exactly');
resetD2();K::$schemaRevision=new RevisionRecord(200,901,['main'=>new SchemaContent($wire)]);K::$history[200]=K::$schemaRevision;
K::$readOnly=true;d2refuses(fn()=>(new InternalFieldKeyWriter())->apply($title),'Key writer respects read-only');d2check(K::$page->saveCalls===0,'Read-only creates no migration revision');
resetD2();K::$schemaRevision=new RevisionRecord(200,901,['main'=>new SchemaContent($wire)]);K::$history[200]=K::$schemaRevision;
K::$page->beforeSave=static function($page){$page->revision=new RevisionRecord(999,943,$page->revision->content);};
d2refuses(fn()=>(new InternalFieldKeyWriter())->apply($title),'Concurrent parent edit refuses stale key write');
d2check(!isset(K::$page->revision->getContent('sws')->getPageSubjects()->getMainSubject()->getFieldNotes()['Z1']),'Rejected stale write did not rewrite notes');
echo "$checks D8 worker/source/notes/idempotence/slot/CAS checks passed (controlled host).\n";
