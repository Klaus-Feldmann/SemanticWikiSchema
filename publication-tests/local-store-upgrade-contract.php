<?php

declare(strict_types=1);
require __DIR__.'/direct-field-read-contract.php';

use CollectorHarness\State as U;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyDefinitions,PropertyDefinition,PropertyName,SchemaReference};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList,SubjectMap};
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\EffectiveFieldLookup;
use SWSBaseBridge\{SourceSnapshotCollector,DataViewLookup,ExportPageDataViewApi};

$u=0;function upgradeCheck(bool $ok,string $why):void{global $u;$u++;if(!$ok)throw new RuntimeException($why);}
function uclaim(string $type,mixed $value):array{return ['rank'=>'normal','mainsnak'=>['snaktype'=>'value','datatype'=>$type,'datavalue'=>['type'=>in_array($type,['string','commonsMedia'],true)?'string':$type,'value'=>$value]]];}
U::$mode='store';U::$requests=[];U::$cache=[];U::$cacheCalls=0;U::$failIds=[];U::$denied=[];U::$publish=true;U::$publishedId=null;U::$localEnabled=true;
U::$cachedQ=[943=>'Q1'];U::$shortdesc=null;
U::$entities=[
 'wikidata'=>[
  'Q41390'=>['id'=>'Q41390','labels'=>['de'=>['value'=>'Kurt Gödel']],'descriptions'=>['de'=>['value'=>'Mathematiker']],
   'claims'=>['P19'=>[uclaim('wikibase-item',['id'=>'Q14960'])],'P1477'=>[uclaim('string','Quellenname')],
    'P569'=>[uclaim('time',['time'=>'+1906-04-28T00:00:00Z','precision'=>11,'calendarmodel'=>'http://www.wikidata.org/entity/Q1985727'])],
    'P18'=>[uclaim('commonsMedia','Gödel.jpg')]]],
  'Q14960'=>['id'=>'Q14960','labels'=>['de'=>['value'=>'Brünn']]]],
 'wikibase-local'=>['Q500'=>['id'=>'Q500','claims'=>['P19'=>[uclaim('string','Lokaler Wert')]]],'P19'=>['id'=>'P19','datatype'=>'string']]];
foreach(['P19'=>'wikibase-item','P1477'=>'string','P569'=>'time','P18'=>'commonsMedia','P20'=>'string'] as $pid=>$datatype){U::$entities['wikidata'][$pid]=['id'=>$pid,'datatype'=>$datatype,'labels'=>['de'=>['value'=>$pid]]];}
$up=[];
foreach(['Ort'=>['text','wikidata','P19'],'Name'=>['text','wikidata','P1477'],'Datum'=>['historicalDate','wikidata','P569'],
 'Bild'=>['image','wikidata','P18'],'Lokal'=>['text','wikibase-local','P19'],'Fehlt'=>['text','wikidata','P20']] as $name=>[$type,$source,$pid]){
 $up[$name]=PropertyDefinition::fromJson(['type'=>$type,'externalProperty'=>['source'=>$source,'id'=>$pid],
  'fieldNotes'=>['type'=>'wikitext','annotate'=>true]],$registry);
}
SWSExtension::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions($up),false);
$oldSubject=new Subject(new SubjectId('s11111111111111'),null,SchemaReference::local(new SchemaName('Person')),
 new StatementList([new Statement(new PropertyName('Name'),'text',new StringValue('Eigene Angabe'))]),['Ort'=>'[[Brünn]] gemäß Forschung.']);
setStorePage($oldSubject,101);$oldSlot=U::$revision->getContent('sws')->getText();
upgradeCheck(!str_contains($oldSlot,'sourceSnapshot'),'Start with real serialized old subject without stored sources');
$pending=(new EffectiveFieldLookup($context))->read($title);
upgradeCheck($pending['fields'][0]['external']['recorded']===false&&U::$requests===[],'Old data remains readable; no hidden first-view fetch');
function ucollect():array{
 $processing=new ProcessingContext(new MediaWiki\Title\Title());$processing->output=new MediaWiki\Parser\ParserOutput(['unlinkedwikibase_id'=>'Q41390']);
 return $processing->run(fn()=>(new SourceSnapshotCollector(new MediaWiki\Context\TestContext()))->collect(new MediaWiki\Title\Title(),SWSExtension::$schema,'de'));
}
$started=hrtime(true);$fresh=ucollect();$collectMs=(hrtime(true)-$started)/1e6;$initialRequests=count(U::$requests);
$source=SourceSnapshot::recordAttempt($oldSubject->id,943,101,[901=>100],'de','2026-11-12T10:00:00Z',$fresh['fields'],$fresh['identities']);
$oldSubject->setSourceSnapshot($source);setStorePage($oldSubject,102);$newSlot=U::$revision->getContent('sws')->getText();
$reloaded=U::$revision->getContent('sws')->getPageSubjects()->getMainSubject();
upgradeCheck($initialRequests===3&&U::$cacheCalls===0,'Actual collector uses three batched fresh requests, not WAN cache');
upgradeCheck($reloaded->id->text==='s11111111111111'&&$reloaded->getSchemaReference()->getText()==='Person','ID and schema name unchanged after real serialization');
upgradeCheck($reloaded->getStatements()->asArray()['Name']->getValue()->toScalars()===['Eigene Angabe']&&$reloaded->getFieldNotes()['Ort']==='[[Brünn]] gemäß Forschung.','Own values and notes survive upgrade');
$fields=array_column((new EffectiveFieldLookup($context))->read($title,['includeImages'=>true])['fields'],null,'name');
upgradeCheck($fields['Ort']['values'][0]['value']==='Brünn'&&$fields['Lokal']['values'][0]['value']==='Lokaler Wert','Actual collected local and external P19 remain distinct');
upgradeCheck($fields['Name']['values'][0]['value']==='Eigene Angabe'&&$fields['Name']['external']['values'][0]['value']==='Quellenname','All bindings captured despite own-value precedence');
upgradeCheck($fields['Datum']['values'][0]['metadata']['sourceTime']['precision']===11&&$fields['Bild']['values'][0]['metadata']['repository']==='commons','Typed date precision/calendar and image repository survive collector/storage/read chain');
upgradeCheck($fields['Fehlt']['values']===[]&&$fields['Fehlt']['sourceStatus']==='missing','Absent claim is recorded state, not processing failure');
$beforeRead=count(U::$requests);$readStarted=hrtime(true);
for($i=0;$i<20;$i++){
 $read=(new EffectiveFieldLookup($context))->read($title);
 $view=(new DataViewLookup($context))->read($title,'de');
 upgradeCheck($view['subjects'][0]['fields'][0]['effectiveValues'][0]['label']===$read['fields'][0]['values'][0]['value'],'View and direct query agree on repeated local reads');
}
$readMs=(hrtime(true)-$readStarted)/1e6;
foreach(['json','turtle','trig'] as $format){$response=(new ExportPageDataViewApi(['format'=>$format,'language'=>'de'],$context->getAuthority()))->run(943);
 upgradeCheck($response->status===200&&str_contains((string)$response->body,'Brünn'),"Actual captured values export in $format");}
upgradeCheck(count(U::$requests)===$beforeRead,'Twenty direct/view read pairs and three exports add zero source requests');
upgradeCheck(U::$revision->getContent('sws')->getText()===$newSlot,'Repeated reads create no slot writes');
// Outage: use real collector and serializers again, not fabricated retained-field results.
U::$failIds=['Q41390'];$failed=ucollect();$retry=SourceSnapshot::recordAttempt($reloaded->id,943,102,[901=>100],'de','2026-11-12T10:01:00Z',$failed['fields'],$failed['identities'],$source);
$reloaded->setSourceSnapshot($retry);setStorePage($reloaded,103);
$f=array_column((new EffectiveFieldLookup($context))->read($title)['fields'],null,'name');
upgradeCheck($f['Ort']['values'][0]['value']==='Brünn'&&$f['Ort']['sourceStatus']==='unavailable'&&$f['Ort']['external']['retained'],'Actual failed update retains last good field');
upgradeCheck($f['Name']['values'][0]['value']==='Eigene Angabe'&&$f['Lokal']['values'][0]['value']==='Lokaler Wert','Outage neither overwrites manual data nor blocks other source');
$beforeSmw=count(U::$requests);
(new ProfessionalWiki\SWS\EntryPoints\Semantic\AutomaticAnnotations())->apply($title,new MediaWiki\Parser\ParserOutput([]),new MediaWiki\Parser\Parser(),SWSExtension::$schema,
 U::$revision->getContent('sws')->getPageSubjects(),$reloaded,new MediaWiki\User\User(),fn()=>SWSExtension::$schema);
$plan=ProfessionalWiki\SWS\EntryPoints\Semantic\SmwAnnotationApplier::$plan;
upgradeCheck(in_array('Person:Ort=Brünn',$plan->rows,true)&&count(array_filter($plan->rows,fn($row)=>str_starts_with($row,'Person:Ort (AM)=')))===2,'Actual annotation entry uses retained source plus separate author/source notes');
upgradeCheck(count(U::$requests)===$beforeSmw,'Actual SMW planning after outage adds no source requests');
// Successful empty retrieval preserves old value, recovery updates it.
U::$failIds=[];unset(U::$entities['wikidata']['Q41390']['claims']['P19']);$missing=ucollect();
$empty=SourceSnapshot::recordAttempt($reloaded->id,943,103,[901=>100],'de','2026-11-12T10:02:00Z',$missing['fields'],$missing['identities'],$retry);
$reloaded->setSourceSnapshot($empty);setStorePage($reloaded,104);
$f=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort'])['fields'][0];
upgradeCheck($f['values'][0]['value']==='Brünn'&&$f['sourceStatus']==='missing','Successful empty result retains prior value and updates status');
U::$entities['wikidata']['Q41390']['claims']['P19']=[uclaim('wikibase-item',['id'=>'Q14960'])];U::$entities['wikidata']['Q14960']['labels']['de']['value']='Brno';
$recovery=ucollect();$latest=SourceSnapshot::recordAttempt($reloaded->id,943,104,[901=>100],'de','2026-11-12T10:03:00Z',$recovery['fields'],$recovery['identities'],$empty);
$reloaded->setSourceSnapshot($latest);setStorePage($reloaded,105);$f=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort'])['fields'][0];
upgradeCheck($f['values'][0]['value']==='Brno'&&$f['sourceStatus']==='resolved'&&$f['sourceNote']===null,'Recovery replaces retained value and removes only source warning');
upgradeCheck($f['authorNote']==='[[Brünn]] gemäß Forschung.','Recovery does not alter researcher note');
$metrics=['scope'=>'controlled source/MediaWiki/SMW host; not production timing or DB revision storage','old_slot_bytes'=>strlen($oldSlot),
 'first_store_slot_bytes'=>strlen($newSlot),'initial_capture_requests'=>$initialRequests,'initial_capture_ms'=>$collectMs,
 'direct_and_view_read_pairs'=>20,'read_pairs_ms'=>$readMs,'exports'=>3,'source_requests_during_reads'=>0,
 'read_slot_writes'=>0,'process_peak_bytes'=>memory_get_peak_usage(true)];
if(($path=getenv('SWS_D5_METRICS_OUTPUT'))!==false){file_put_contents($path,json_encode($metrics,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n");}
echo "$u D5 collector/upgrade/serialization/read/export/SMW/failure/recovery checks passed (controlled host, no live database).\n";
