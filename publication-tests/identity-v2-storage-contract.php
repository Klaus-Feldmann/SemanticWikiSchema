<?php

declare( strict_types = 1 );
require __DIR__.'/bootstrap.php';
require __DIR__.'/fixtures/SqliteIdentityConnection.php';

use IdentityHarness\SqliteIdentityConnection;
use ProfessionalWiki\SWS\Application\Identity\{SchemaIdentityPreparation, IdentityMetadataWriter};
use ProfessionalWiki\SWS\Application\{NewSubjectIdResolver, SubjectIdMinter, StatementListBuilder, DependentSubjectBuilder};
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Domain\Identity\{LocalizedIdentity, SchemaId, EntityId, IdentityKind};
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaName, SchemaReference, SchemaReferenceParser, SchemaReferenceNormalizer, PropertyDefinitions};
use ProfessionalWiki\SWS\Domain\Subject\{Subject, SubjectId, SubjectIdParser, SubjectLabel, SubjectMap, StatementList};
use ProfessionalWiki\SWS\Domain\Page\{PageSubjects, PageId, PageIdentifiers, DependentSubjectRules};
use ProfessionalWiki\SWS\Domain\Source\{SourceRegistry, SourceSnapshot};
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
use ProfessionalWiki\SWS\Persistence\MediaWiki\{SchemaPersistenceDeserializer, SchemaContentValidator};
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{SubjectContentDataSerializer, SubjectContentDataDeserializer, StatementDeserializer};
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\SWS\Tests\TestDoubles\{InMemoryPageIdentifiersLookup, InMemoryPageIdentifiersResolver, InMemorySubjectRepository, InMemorySource, StubPageReadAuthorizer, SpySubjectWriteAuthorizer};
use ProfessionalWiki\SWS\Infrastructure\IdGenerator;
use ProfessionalWiki\SWS\Application\Validation\{SubjectValidator, ProposedSubjectValidator, DependentSubjectValidator};
use ProfessionalWiki\SWS\Application\Actions\CreateSubject\{CreateSubjectAction, CreateSubjectRequest};
use ProfessionalWiki\SWS\Presentation\RestCreateSubjectPresenter;

$count=0;
function v2check(bool $ok,string $why):void { global $count;$count++;if(!$ok)throw new RuntimeException($why); }
function v2refused(Closure $run,string $why):void { try{$run();}catch(InvalidArgumentException|RuntimeException $error){v2check(true,$why);return;}throw new RuntimeException('Not refused: '.$why); }
$path=tempnam(sys_get_temp_dir(),'sws-v2-');
try {
 $db=new SqliteIdentityConnection($path);
 foreach(['sws_id_counter','sws_identity','sws_identity_alias']as$table){$db->pdo->exec(str_replace('/*_*/','test_',file_get_contents(__DIR__.'/../sql/sqlite/'.$table.'.sql')));}
 foreach(IdentityKind::cases()as$kind){$db->insertIgnore('sws_id_counter',['swsic_kind'=>$kind->value,'swsic_value'=>0]);}
 $registry=new SqlIdentityRegistry($db);$owner=hash('sha256','Nikolaos');$other=hash('sha256','Other author');
 $normalizer=new class implements SchemaReferenceNormalizer { public function normalize(SchemaReference $reference):SchemaReference{return $reference;} };
 $parser=new SchemaReferenceParser('aewi',$normalizer);$idParser=new SubjectIdParser('aewi');
 $types=PropertyTypeRegistry::withCoreTypes($parser);$logger=new Psr\Log\NullLogger();
 $schemaReader=new SchemaPersistenceDeserializer($types);$schemaWriter=new SchemaPresentationSerializer();
 $writer=new SubjectContentDataSerializer();$reader=new SubjectContentDataDeserializer(new StatementDeserializer($types,$idParser),$logger,$parser);
 $terms=new LocalizedIdentity('de',['de'=>'Person','en'=>'Person'],['de'=>'Personendaten','en'=>'Biographical data']);
 v2check($terms->label('fr')==='Person'&&$terms->description('en')==='Biographical data','Language selection and own descriptions');
 foreach([fn()=>new LocalizedIdentity('DE'),fn()=>new LocalizedIdentity('de',['de'=>123]),fn()=>new LocalizedIdentity('de',[],['en'=>"bad\0text"])]as$bad){v2refused($bad,'Invalid language metadata');}
 $prep=new SchemaIdentityPreparation($registry,$owner,'de');
 $schemaInput=json_encode(['identityLanguage'=>'de','labels'=>['de'=>'Person','en'=>'Person'],'descriptions'=>['de'=>'Personendaten','en'=>'Person data'],'propertyDefinitions'=>(object)[]]);
 $one=$prep->create($schemaInput,'create:first');$two=$prep->create($schemaInput,'create:second');
 v2check($one['id']->text==='S1'&&$two['id']->text==='S2','Duplicate labels, distinct IDs');
 v2check($prep->create($schemaInput,'create:first')['id']->equals($one['id']),'Idempotent schema creation preparation');
 $schema=$schemaReader->deserialize(new SchemaName('S1'),$one['json']);
 v2check($schema->getId()?->text==='S1'&&$schema->getIdentity()?->labels['en']==='Person','New schema metadata persisted');
 v2check($schema->getReference()->toJson()===['id'=>'S1'],'Schema emits ID reference');
 $json=$schemaWriter->serialize($schema);v2check(SchemaContentValidator::newInstance()->validate($json),'Version 2 schema JSON is structurally valid');
 v2check($schemaReader->deserialize(new SchemaName('S1'),$json)->getIdentity()?->descriptions['en']==='Person data','Schema metadata survives serialization');
 $renamed=json_decode($one['json'],true);$renamed['labels']['de']='Personen';$renamed['propertyDefinitions']=(object)[];
 $renamedSchema=$schemaReader->deserialize(new SchemaName('S1'),json_encode($renamed));
 v2check($renamedSchema->getId()?->equals($schema->getId())===true&&$renamedSchema->getIdentity()?->labels['de']==='Personen','Renaming retains schema identity');
 v2refused(fn()=>$schemaReader->deserialize(new SchemaName('S99'),$one['json']),'Schema page/identity mismatch rejected');
 $withRelation=json_decode($one['json'],true);$withRelation['propertyDefinitions']=['Kind'=>['type'=>'relation','targetSchemas'=>[['id'=>'S2']],'multiple'=>true]];
 v2check(SchemaContentValidator::newInstance()->validate(json_encode($withRelation)),'ID-based relation target is accepted by the schema validator');
 v2check($schemaReader->deserialize(new SchemaName('S1'),json_encode($withRelation))->getProperty('Kind')->hasTargetSchema(SchemaReference::identified(new SchemaId('S2'))),'Relation target ID survives schema deserialization');
 $legacySchema=$schemaReader->deserialize(new SchemaName('S1'),'{' . '"description":"Old schema called S1","propertyDefinitions":{}' . '}');
 v2check($legacySchema->getId()===null,'Old schema name S1 remains a name');
 v2check(!$legacySchema->getReference()->equals($schema->getReference()),'Legacy S1 and identified S1 are distinct');
 v2check($parser->fromJson(['id'=>'S1'])->id?->text==='S1'&&$parser->fromJson('S1')->id===null,'Explicit format parsing, no guessing');
 v2refused(fn()=>$parser->fromJson(['id'=>'E1']),'Entity ID is not a schema');
 $source=new InMemorySource();$source->addSchema($legacySchema);$sources=new SourceRegistry('aewi');$sources->registerSource('aewi',$source);$resolver=new SchemaResolver($sources,$logger);
 v2check($resolver->getSchema($schema->getReference())===null,'An ID reference cannot resolve to a merely same-named legacy page');
 $source->addSchema($schema);
 v2check($resolver->getSchema($schema->getReference())?->getId()?->text==='S1','Authoritative ID schema resolves');
 $generator=new class implements IdGenerator {public function generate():string{return '11111111111111';}};
 $lookup=new InMemoryPageIdentifiersLookup();$newIds=new NewSubjectIdResolver($idParser,$generator,$lookup,$registry,$owner);
 $minter=new SubjectIdMinter($generator,$registry,$owner);$batch=$minter->mint(2,'batch-a');
 v2check($batch[0]->text==='E1'&&$batch[1]->text==='E2','Production minter uses E series');
 v2check(array_map(fn($id)=>$id->text,$minter->mint(2,'batch-a'))===['E1','E2'],'Batch retry stable');
 v2check($newIds->resolve('aewi:E1')->text==='E1','Owner can use qualified pre-minted ID');
 $otherIds=new NewSubjectIdResolver($idParser,$generator,$lookup,$registry,$other);
 v2refused(fn()=>$otherIds->resolve('E1'),'Another author cannot take an unused reservation through the creation API');
 v2refused(fn()=>$newIds->resolve('E999'),'Unreserved client ID rejected');
 v2refused(fn()=>$newIds->resolve('s11111111111111'),'Client cannot create a fresh random legacy ID');
 v2check(str_starts_with($newIds->resolve(null)->text,'E'),'Automatic creation reserves E ID');
 $subject=IdentityMetadataWriter::initialize(new Subject($batch[0],new SubjectLabel('Person'),$schema->getReference(),new StatementList(),['Geburtsort'=>'[[Brünn]]']), 'de', $terms->toJson()===[]?null:['identityLanguage'=>'de','labels'=>$terms->labels,'descriptions'=>$terms->descriptions]);
 $snapshot=SourceSnapshot::recordAttempt($subject->id,943,100,[901=>10],'de','2026-11-12T12:00:00Z',[]);
 $subject->setSourceSnapshot($snapshot);
 $page=new PageSubjects($subject,new SubjectMap());$saved=$writer->serialize($page);$data=json_decode($saved,true);
 v2check($data['formatVersion']===2&&$data['mainSubject']==='E1'&&isset($data['subjects']['E1']),'Versioned entity storage uses E key and main reference');
 $loaded=$reader->deserialize($saved)->getMainSubject();
 v2check($loaded?->getIdentity()?->descriptions['en']==='Biographical data','Descriptions survive storage');
 v2check($loaded?->getSourceSnapshot()?->toJson()===$snapshot->toJson()&&$loaded?->getFieldNotes()===['Geburtsort'=>'[[Brünn]]'],'Snapshot and research notes survive');
 $loaded->setLabel(new SubjectLabel('Neuer Name'));
 v2check($loaded->getIdentity()?->labels['de']==='Neuer Name'&&$loaded->getIdentity()?->labels['en']==='Person','Old client label edit preserves other languages');
 $copy=$loaded->withStatements(new StatementList());
 v2check($copy->getIdentity()===$loaded->getIdentity()&&$copy->getSourceSnapshot()===$loaded->getSourceSnapshot(),'Derived subject copy retains identity metadata and source');
 v2check(SubjectContentDataDeserializer::deserializeSubjectIds($saved)===['E1'],'Derived page index reads E IDs from v2');
 unset($data['formatVersion']);$unversioned=json_encode($data);
 v2refused(fn()=>$reader->deserialize($unversioned),'New E IDs require an explicit content version');
 v2check(SubjectContentDataDeserializer::deserializeSubjectIds($unversioned)===[],'Index cannot expose an unversioned E ID');
 $data['formatVersion']=99;v2refused(fn()=>$reader->deserialize(json_encode($data)),'Unknown format does not silently drop data');
 $legacy=new Subject(new SubjectId('s11111111111111'),null,SchemaReference::local(new SchemaName('Person')),new StatementList());
 $oldJson=$writer->serialize(new PageSubjects($legacy,new SubjectMap()));
 v2check(!str_contains($oldJson,'formatVersion')&&$reader->deserialize($oldJson)->getMainSubject()?->id->text==='s11111111111111','Legacy-only data remains old format');
 $mixed=$writer->serialize(new PageSubjects($subject,new SubjectMap($legacy)));
 v2check(count($reader->deserialize($mixed)->getAllSubjects()->asArray())===2,'Mixed legacy/new page retains both');
 // Exercise the actual CreateSubjectAction with the real validator, builder, minter and serializer.
 $repo=new InMemorySubjectRepository();$schemaSource=new InMemorySource();$schemaSource->addSchema($schema);
 $sourceRegistry=new SourceRegistry('aewi');$sourceRegistry->registerSource('aewi',$schemaSource);$schemaResolver=new SchemaResolver($sourceRegistry,$logger);
 $statementBuilder=new StatementListBuilder($types,$generator,$idParser);$rules=new DependentSubjectRules($schemaResolver);
 $dependent=new DependentSubjectBuilder($newIds,$parser,$schemaResolver,$statementBuilder,$rules,'de');
 $proposed=new ProposedSubjectValidator($schemaResolver,new SubjectValidator($types,$repo,$sourceRegistry));
 $presenter=new RestCreateSubjectPresenter();$writeAuth=new SpySubjectWriteAuthorizer(true);$readAuth=new StubPageReadAuthorizer(true);
 $action=new CreateSubjectAction($presenter,$repo,$newIds,$readAuth,$writeAuth,$statementBuilder,$schemaResolver,$proposed,
  new InMemoryPageIdentifiersResolver([new PageIdentifiers(new PageId(943),'Kurt Gödel',0)]),$parser,$dependent,new DependentSubjectValidator($proposed),true,'de');
 $action->createSubject(new CreateSubjectRequest(943,true,'Kurt Gödel','S1',[],identity:['identityLanguage'=>'de','labels'=>['de'=>'Kurt Gödel','en'=>'Kurt Gödel'],'descriptions'=>['de'=>'Mathematiker','en'=>'Mathematician']]));
 $created=$repo->getSubjectsByPageId(new PageId(943))->getMainSubject();
 v2check($presenter->getStatusCode()===201&&$created?->id->isSequential()===true,'Actual create action persists a reserved entity');
 v2check($created?->getSchemaReference()->toJson()===['id'=>'S1'],'Actual creation promotes schema from authoritative ID content');
 v2check($created?->getIdentity()?->descriptions['en']==='Mathematician','Actual create action accepts multilingual identity data');
 v2check($reader->deserialize($writer->serialize($repo->getSubjectsByPageId(new PageId(943))))->getMainSubject()?->id->equals($created->id)===true,'Actual creation serializes and reads back');
 $createdResponse=$presenter->getJsonArray()['subject'];
 $before=(int)$db->pdo->query("SELECT COUNT(*) FROM test_sws_identity")->fetchColumn();$readAuth->allowed=false;
 $action->createSubject(new CreateSubjectRequest(944,true,'Hidden','S1',[]));
 v2check((int)$db->pdo->query("SELECT COUNT(*) FROM test_sws_identity")->fetchColumn()===$before,'Read denial does not allocate an ID');
 $responseBody=json_decode(json_encode($createdResponse),true);
 $readBackMetadata=LocalizedIdentity::fromRequest($responseBody);
 v2check($readBackMetadata['descriptions']['en']==='Mathematician','HTTP read metadata can be reused in a write request');
 v2check(LocalizedIdentity::fromRequest(['label'=>'Only default label','statements'=>[]])===null,'An old client does not implicitly replace translations');
 v2refused(fn()=>LocalizedIdentity::fromJson(LocalizedIdentity::fromRequest(['labels'=>['de'=>'Partial']])),'Partial metadata cannot silently erase other fields');
 // The ordinary MediaWiki schema content handler uses this same policy.
 $schemaData=json_decode($one['json'],true);$policy=\ProfessionalWiki\SWS\Application\Identity\SchemaIdentityWritePolicy::class;
 $policy::validate(new SchemaName('S1'),null,$schemaData,$registry,$owner);
 v2check(true,'A new reserved S schema is admitted');
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),null,$schemaData,$registry,$other),'Another author cannot use the schema reservation');
 $registry->markUsed(new SchemaId('S1'));
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),null,$schemaData,$registry,$owner),'A deleted issued schema ID cannot be freshly created');
 $changedSchema=$schemaData;$changedSchema['labels']['de']='Umbenannt';
 $policy::validate(new SchemaName('S1'),$schemaData,$changedSchema,$registry,$other);
 v2check(true,'An authorized ordinary edit changes labels without changing schema identity, independently of the reservation owner');
 $different=$changedSchema;$different['id']='S2';
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),$schemaData,$different,$registry,$owner),'Existing schema ID cannot be changed');
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),$schemaData,['propertyDefinitions'=>[]],$registry,$owner),'Existing v2 schema cannot be downgraded');
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),['propertyDefinitions'=>[]],$schemaData,$registry,$owner),'An old schema called S1 cannot be reinterpreted by a normal edit');
 $emptyLabel=$changedSchema;$emptyLabel['labels']=[];
 v2refused(fn()=>$policy::validate(new SchemaName('S1'),$schemaData,$emptyLabel,$registry,$owner),'Schema edits cannot remove every human-readable label');
 $policy::validate(new SchemaName('Person'),['propertyDefinitions'=>[]],['propertyDefinitions'=>[]],$registry,$owner);
 v2check(true,'Existing legacy schemas remain editable');
 v2refused(fn()=>$policy::validate(new SchemaName('Person'),null,['propertyDefinitions'=>[]],$registry,$owner),'New ordinary schema creation requires the new identity path');
 // A fresh creation cannot recycle an issued/deleted ID. An authorized editor may still edit a live entity.
 $registry->markUsed(new EntityId('E1'));
 v2check(!$registry->canCreate(new EntityId('E1'),$owner)&&$registry->isReservedForOwner(new EntityId('E1'),$owner),'Issued ID remains reserved but is unavailable for fresh creation');
 v2refused(fn()=>$newIds->resolve('E1'),'A deleted issued ID cannot be used for new creation');
 $liveLookup=new InMemoryPageIdentifiersLookup([[new SubjectId('E1'),new PageIdentifiers(new PageId(943),'Kurt Gödel',0)]]);
 v2check((new NewSubjectIdResolver($idParser,$generator,$liveLookup,$registry,$other))->resolve('E1')->text==='E1','Existing data can be edited by another authorized author');
 // Actual replace path: clients which send no multilingual block retain translations and snapshots.
 $readAuth->allowed=true;
 $created->setSourceSnapshot(SourceSnapshot::recordAttempt($created->id,943,101,[901=>10],'de','2026-11-12T12:00:00Z',[]));
 $pageLookup=new class($repo) implements \ProfessionalWiki\SWS\Application\PageSubjectsAtRevisionLookup {
  public function __construct(private InMemorySubjectRepository $repo){}
  public function getPageSubjects(PageId $id):PageSubjects{return $this->repo->getSubjectsByPageId($id);}
 };
 $editLookup=new InMemoryPageIdentifiersLookup([[$created->id,new PageIdentifiers(new PageId(943),'Kurt Gödel',0)]]);
 $replacePresenter=new \ProfessionalWiki\SWS\Presentation\RestReplaceSubjectPresenter();
 $replace=new \ProfessionalWiki\SWS\Application\Actions\ReplaceSubject\ReplaceSubjectAction($repo,
  new \ProfessionalWiki\SWS\Application\SubjectHostingPageResolver($editLookup,$readAuth),$writeAuth,$statementBuilder,$schemaResolver,$proposed,$dependent,new DependentSubjectValidator($proposed),
  new \ProfessionalWiki\SWS\Application\SubjectResponseItemFactory($pageLookup,new \ProfessionalWiki\SWS\Application\HostSubjectBuilder($rules)),$replacePresenter,true);
 $replace->replace($created->id,'Gödel',[],null);
 $edited=$repo->getSubject($created->id);
 v2check($replacePresenter->getStatusCode()===200&&$edited->getIdentity()?->labels['de']==='Gödel'&&$edited->getIdentity()?->labels['en']==='Kurt Gödel','Actual replace keeps unedited translations');
 v2check($edited->getIdentity()?->descriptions['en']==='Mathematician'&&$edited->getSourceSnapshot()?->getSubjectId()===$created->id->text,'Actual replace keeps descriptions and local source snapshot');
 $replace->replace($created->id,'Gödel',[],null,identity:['identityLanguage'=>'de','labels'=>['de'=>'Gödel','en'=>'Kurt Goedel'],'descriptions'=>['de'=>'Mathematiker','en'=>'Logician']]);
 v2check($repo->getSubject($created->id)->getIdentity()?->labels['en']==='Kurt Goedel'&&$repo->getSubject($created->id)->getIdentity()?->descriptions['en']==='Logician','Explicit multilingual changes are stored');
 $replace->replace($created->id,'Gödel',[],null,identity:['identityLanguage'=>'en','labels'=>['de'=>'Gödel','en'=>'Kurt Goedel'],'descriptions'=>['de'=>'Mathematiker','en'=>'Logician']]);
 v2check($repo->getSubject($created->id)->getIdentity()?->language==='en'&&$repo->getSubject($created->id)->getIdentity()?->labels['de']==='Gödel','An explicit metadata edit can change the base language while retaining translations');
 // Both page-first and subject-first creation pass through the same new allocator.
 if(!defined('NS_MAIN'))define('NS_MAIN',0);
 foreach([false,true]as$subjectFirst){
  $pagePresenter=new \ProfessionalWiki\SWS\Presentation\RestCreateSubjectPagePresenter();
  $pageAction=new \ProfessionalWiki\SWS\Application\Actions\CreateSubjectPage\CreateSubjectPageAction($pagePresenter,$repo,$newIds,$writeAuth,$statementBuilder,$schemaResolver,$proposed,new InMemoryPageIdentifiersResolver(),$parser,$dependent,new DependentSubjectValidator($proposed),true,$subjectFirst,0,'de');
  $pageAction->createSubjectPage(new \ProfessionalWiki\SWS\Application\Actions\CreateSubjectPage\CreateSubjectPageRequest('Richard Feynman',null,'S1',[]));
  $answer=$pagePresenter->getJsonArray();
  v2check($pagePresenter->getStatusCode()===201&&str_starts_with($answer['subjectId'],'E'),'Actual page creation uses a reserved E ID');
  v2check($answer['pageTitle']===($subjectFirst?$answer['subjectId']:'Richard Feynman'),'Page-first and subject-first naming is retained');
  v2check($answer['subject']['labels']->de==='Richard Feynman','New page entity initializes its own language metadata');
 }
 // Dependent edits preserve metadata, notes and source data when no corresponding replacement is sent.
 $childSchema=new Schema(new SchemaName('S2'),'',new PropertyDefinitions(['Ort'=>\ProfessionalWiki\SWS\Domain\Schema\Property\TextProperty::fromPartialJson(new \ProfessionalWiki\SWS\Domain\Schema\PropertyCore('',false,null,fieldNotes:['type'=>'text']),[])]),true,new SchemaId('S2'),new LocalizedIdentity('de',['de'=>'Notiz']));
 $schemaSource->addSchema($childSchema);
 $childId=$minter->mint(1,'child')[0];
 $child=new Subject($childId,null,$childSchema->getReference(),new StatementList(),['Ort'=>'Forschungsnotiz'],identity:new LocalizedIdentity('de',[],['en'=>'Research context']));
 $child->setSourceSnapshot(SourceSnapshot::recordAttempt($childId,943,101,[902=>5],'de','2026-11-12T12:00:00Z',[]));
 $host=$created->withStatements(new StatementList([new \ProfessionalWiki\SWS\Domain\Statement(new \ProfessionalWiki\SWS\Domain\Schema\PropertyName('Kind'),'relation',new \ProfessionalWiki\SWS\Domain\Value\RelationValue(new \ProfessionalWiki\SWS\Domain\Relation\Relation(new \ProfessionalWiki\SWS\Domain\Relation\RelationId('r11111111111111'),$childId)))]));
 $beforePage=new PageSubjects($host,new SubjectMap($child));
 $depBuilder=new DependentSubjectBuilder($newIds,$parser,$schemaResolver,$statementBuilder,new DependentSubjectRules($schemaResolver),'de');
 $children=$depBuilder->build([['id'=>$childId->text,'schema'=>['id'=>'S2'],'statements'=>[]]],$host->id,$beforePage);
 $childAfter=$children->getSubject($childId);
 v2check($childAfter?->getIdentity()?->descriptions['en']==='Research context','Dependent edit preserves omitted identity metadata');
 v2check($childAfter?->getFieldNotes()===['Ort'=>'Forschungsnotiz']&&$childAfter?->getSourceSnapshot()?->toJson()===$child->getSourceSnapshot()->toJson(),'Dependent edit preserves omitted notes and snapshot');
 v2check($childAfter?->getSchemaReference()->toJson()===['id'=>'S2'],'Dependent entity stores an explicit schema ID');
 print "$count D2 ID/metadata/schema/entity/storage/actual-create checks passed (real SQLite allocation, controlled page repository).\n";
}finally{@unlink($path);}
