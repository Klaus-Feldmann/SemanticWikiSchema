<?php
// Real read-model and persistence paths; source snapshots and localized messages are controlled inputs.
require dirname(__DIR__).'/tests/standalone/autoload.php';
use ProfessionalWiki\SWS\Application\Semantic\{FieldValues,AnnotationPlanner,FieldNoteAnnotations,FieldSnapshot};
use ProfessionalWiki\SWS\Domain\FieldNotes;
use ProfessionalWiki\SWS\Domain\Schema\{PropertyCore,PropertyDefinitions,Schema,SchemaName,PropertyDefinition};
use ProfessionalWiki\SWS\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\SWS\Domain\Subject\{SubjectId,SubjectIdParser,StatementList,Subject};
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{SubjectContentDataSerializer,SubjectContentDataDeserializer};
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
function wfMessage($key,...$args){return new class($key,$args){public function __construct(private $key,private $args){}public function inContentLanguage(){return $this;}public function text(){return match($this->key){'sws-notes-property-suffix'=>' (AM)','sws-notes-source-unavailable'=>implode(' ',$this->args).' derzeit nicht abrufbar.',default=>$this->key.':'.implode(' ',$this->args)};}};}
$n=0;function check($ok,$why){global $n;$n++;if(!$ok)throw new RuntimeException($why);}
$binding=['source'=>'wikidata','id'=>'P19'];
$schema=['name'=>'Person','properties'=>[
 ['name'=>'Geburtsort','type'=>'text','externalProperty'=>$binding,'fieldNotes'=>['type'=>'wikitext','annotate'=>true]],
 ['name'=>'Eigen','type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P20'],'fieldNotes'=>['type'=>'text','annotate'=>true]],
 ['name'=>'Leer','type'=>'text','fieldNotes'=>['type'=>'text','annotate'=>true]]
]];
$subject=['id'=>'s11111111111111','statements'=>['Eigen'=>['values'=>['Händischer Wert']]],'fieldNotes'=>['Geburtsort'=>'[[Brünn]] gemäß Quelle.','Leer'=>'Noch nicht erforscht.']];
$calls=0;$state='unavailable';
$read=function()use(&$calls,&$state,$subject){$calls++;return ['version'=>1,'scope'=>'current','mainSubjectId'=>$subject['id'],'fields'=>[
 ['name'=>'Geburtsort','source'=>'wikidata','propertyId'=>'P19','status'=>$state,'values'=>$state==='resolved'?[['kind'=>'text','value'=>'Brünn']]:[['kind'=>'text','value'=>'Staler Wert darf nicht erscheinen']]],
 ['name'=>'Eigen','source'=>'wikidata','propertyId'=>'P20','status'=>'unavailable','values'=>[]]
]];};
$reader=new FieldValues($schema,$subject,$read);$result=$reader->collectForAnnotations();
check($calls===1,'One shared external snapshot, not an extra request for notes');
check($result['fields'][0]['status']==='unavailable'&&$result['fields'][0]['values']===[],'Failed source is an empty field outcome');
check($result['fields'][1]['status']==='own'&&$result['fields'][1]['values'][0]['value']==='Händischer Wert','Own value survives other source failure and own binding outage');
$planner=new AnnotationPlanner(['properties'=>[]]);$plan=$planner->build($result);
check($plan->rows===['Eigen=Händischer Wert'],'Only failed derived value omitted, manual annotation remains');
$notes=(new FieldNoteAnnotations)->build($result,['properties'=>['Person'=>['Geburtsort'=>'Geboren in']]]);
check($notes===['Geboren in (AM)=[[Brünn]] gemäß Quelle.','Geboren in (AM)=Wikidata P19 derzeit nicht abrufbar.','Leer (AM)=Noch nicht erforscht.'],'Mapped note name, author note, source note and note for empty field');
check($calls===1,'Note projection made no lookup');
$reader->collect();check($calls===1,'Subsequent reads share the same captured snapshot');
$state='resolved';$fresh=(new FieldValues($schema,$subject,$read))->collectForAnnotations();
check($planner->build($fresh)->rows===['Geburtsort=Brünn','Eigen=Händischer Wert'],'Recovery restores source annotation without affecting manual value');
check(count((new FieldNoteAnnotations)->build($fresh,['properties'=>[]]))===2,'Recovery removes only automatic error note');
$state='missing';$missing=(new FieldValues($schema,$subject,$read))->collectForAnnotations();check($missing['fields'][0]['status']==='missing'&&$missing['fields'][0]['values']===[],'Missing claim is distinct and never consumes stale payload');
$state='unsupported';check((new FieldValues($schema,$subject,$read))->collectForAnnotations()['fields'][0]['status']==='unsupported','Unsupported source type remains a field outcome');
$bad=function()use($subject){return ['version'=>1,'scope'=>'current','mainSubjectId'=>$subject['id'],'fields'=>[['name'=>'Geburtsort','source'=>'wikidata','propertyId'=>'P20','status'=>'unavailable','values'=>[]]]];};
try{(new FieldValues($schema,$subject,$bad))->collectForAnnotations();check(false,'Mismatched property accepted');}catch(InvalidArgumentException){check(true,'Schema/source mismatch still rejected');}
check(FieldNotes::validate(['Leer'=>'  Notiz\n','Entfernt'=>' '])===['Leer'=>'  Notiz\n'],'Author source preserved, cleared note omitted');
try{FieldNotes::validate(['Leer'=>['status'=>'unavailable']]);check(false,'Status object accepted as author note');}catch(InvalidArgumentException){check(true,'Only plain author text accepted');}
$domain=new Subject(new SubjectId('s11111111111111'),null,SchemaReference::local(new SchemaName('Person')),new StatementList);
$domain->setFieldNotes(['Leer'=>'[[Brno]]']);check($domain->withStatements(new StatementList)->getFieldNotes()===['Leer'=>'[[Brno]]'],'Changing field values preserves notes');
$serialized=json_decode((new SubjectContentDataSerializer)->serialize(new PageSubjects($domain,new SubjectMap)),true);
check($serialized['subjects']['s11111111111111']['fieldNotes']===['Leer'=>'[[Brno]]']&&$serialized['subjects']['s11111111111111']['statements']===[],'Note-only subject persists independently of empty values');
$plainSchema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Leer'=>TextProperty::fromPartialJson(new PropertyCore('',false,null,fieldNotes:['type'=>'wikitext','annotate'=>true]),[])]),false);
$snap=FieldSnapshot::reader($plainSchema,$domain,static fn()=>throw new RuntimeException('No source lookup allowed'))->collectForAnnotations();
check((new FieldNoteAnnotations)->build($snap,['properties'=>[]])===['Leer (AM)=[[Brno]]'],'Author-only notes work without source reads');
check(FieldNotes::forSchema(['Leer'=>'Neu'],[], $plainSchema)===['Leer'=>'Neu'],'Enabled field accepts author note');
check(FieldNotes::forSchema(['Alt'=>'Bestehend'],['Alt'=>'Bestehend'],null)===['Alt'=>'Bestehend'],'Unknown/disabled existing notes are retained');
check(FieldNotes::forSchema([],['Alt'=>'Bestehend'],null)===[],'Explicit removal remains possible after schema changes');
try{FieldNotes::forSchema(['Neu'=>'Forged'],[],null);check(false,'Unconfigured note accepted');}catch(InvalidArgumentException){check(true,'Write path requires schema permission for new notes');}
$normalizer=new class implements \ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceNormalizer { public function normalize(\ProfessionalWiki\SWS\Domain\Schema\SchemaReference $ref): \ProfessionalWiki\SWS\Domain\Schema\SchemaReference{return $ref;} };
$parser=new SchemaReferenceParser('aewi',$normalizer);$registry=PropertyTypeRegistry::withCoreTypes($parser);
$definition=PropertyDefinition::fromJson(['type'=>'text','fieldNotes'=>['type'=>'wikitext','annotate'=>true]],$registry);
check($definition->toJson()['fieldNotes']===['type'=>'wikitext','annotate'=>true],'Schema note configuration roundtrip');
try{PropertyDefinition::fromJson(['type'=>'text','fieldNotes'=>['type'=>'html']],$registry);check(false,'Invalid note type accepted');}catch(InvalidArgumentException){check(true,'Unsupported note mode rejected');}
$imageSchema=['name'=>'Person','properties'=>[['name'=>'Bild','type'=>'image','externalProperty'=>['source'=>'wikidata','id'=>'P18'],'fieldNotes'=>['type'=>'text','annotate'=>true]]]];
$imageResult=(new FieldValues($imageSchema,['id'=>'s11111111111111','statements'=>[],'fieldNotes'=>['Bild'=>'Bild prüfen']],static fn()=>throw new RuntimeException('Additional image request forbidden')))->collectForAnnotations();
check((new FieldNoteAnnotations)->build($imageResult,['properties'=>[]])===['Bild (AM)=Bild prüfen'],'Image note does not add an external lookup solely for its status');
$imageResult['sourceFields']=['Bild'=>['source'=>'wikidata','propertyId'=>'P18','status'=>'unavailable','values'=>[]]];
check(count((new FieldNoteAnnotations)->build($imageResult,['properties'=>[]]))===2,'An already-read image source failure is reused without a lookup');
echo "$n notes/manual-values/source-failure/recovery/persistence checks passed (controlled source and localization inputs).\n";
