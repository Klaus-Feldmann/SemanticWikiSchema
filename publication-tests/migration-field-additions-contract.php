<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationFieldDefinitionSelector as Selector,MigrationFieldRepairScope as Scope,MigrationFieldAdditionLog as Log,ReferenceRewriter};

$nAuto=0;function autoCheck(bool $ok,string $why):void{global $nAuto;$nAuto++;if(!$ok)throw new RuntimeException($why);}
$p31=(object)['type'=>'text','required'=>false,'externalProperty'=>(object)['source'=>'wikidata','id'=>'P31']];
$schema=static fn($definition)=>(object)['propertyDefinitions'=>(object)['Klassenzuordnung'=>$definition]];
$selection=Selector::select('Klassenzuordnung',['S10'=>$schema($p31),'S1'=>$schema($p31),'S6'=>$schema((object)['type'=>'text'])],'SWS Migration Feld ergänzt');
autoCheck($selection['sourceSchema']==='S1'&&$selection['definition']->externalProperty->id==='P31'&&!$selection['needsReview'],'Functional source majority chooses stable first schema');
$selection['definition']->externalProperty->id='P1';autoCheck($p31->externalProperty->id==='P31','Selected definition is a copy, not a mutable donor');
$fallback=Selector::select('Datum',['S1'=>(object)['propertyDefinitions'=>(object)['Datum'=>(object)['type'=>'text']]]],'SWS Migration Feld ergänzt');
autoCheck($fallback['sourceSchema']==='S1','A sole valid existing definition can be reused');
$fallback=Selector::select('Neu',[],'SWS Migration Feld ergänzt');autoCheck($fallback['definition']->type==='text'&&$fallback['definition']->default===['SWS Migration Feld ergänzt']&&$fallback['needsReview'],'German fallback has only text configuration and schema default');
$fallback=Selector::select('New',[],'SWS migration field added');autoCheck($fallback['definition']->default===['SWS migration field added'],'English placeholder supplied in schema language');
$fallback=Selector::select('Datum',['S1'=>(object)['propertyDefinitions'=>(object)['Datum'=>(object)['type'=>'text']]]],'marker');
$tie=Selector::select('Klassenzuordnung',['S1'=>$schema($p31),'S2'=>$schema((object)['type'=>'number'])],'marker');autoCheck($tie['needsReview']&&$tie['sourceSchema']===null,'Conflicting functional definitions use explicit placeholder');
$exec=Selector::select('Run',['S1'=>(object)['propertyDefinitions'=>(object)['Run'=>(object)['type'=>'wikitext','wikitext'=>'{{Side effect}}']]]],'marker');autoCheck($exec['needsReview'],'Executable schema Wikitext is not copied as a missing data field');
try{Selector::select('bad|name',[],'marker');throw new LogicException('Bad field accepted');}catch(InvalidArgumentException){autoCheck(true,'Invalid field names refused');}
Scope::record('S1','outside','Page','Caller');$probe=Scope::attempt(static fn()=>null);autoCheck($probe['missing']===[],'Outside requests cannot activate automatic repair');
$probe=Scope::attempt(static function(){Scope::record('S2','Field','Target','Caller');throw new RuntimeException('Field not found: Field');});
autoCheck($probe['missing'][0]['schemaId']==='S2'&&$probe['error'] instanceof RuntimeException,'Controlled failed attempt captures exact schema and field');
autoCheck(Scope::attempt(static fn()=>null)['missing']===[],'Repair scope is cleared after errors');
$path=tempnam(sys_get_temp_dir(),'sws-field-log-');unlink($path);
$log=new Log($path,'secret','plan-a');$entry=['schemaId'=>'S2','field'=>'Field','beforeRevision'=>10,'afterJson'=>'{"propertyDefinitions":{"Field":{"type":"text"}}}'];
$key=$log->prepare($entry);autoCheck($log->entries()[$key]['status']==='pending','Write-ahead record precedes schema commit');
$log=new Log($path,'secret','plan-a');autoCheck($log->prepare($entry)===$key&&count($log->entries())===1,'Interrupted report resumes without duplicate entry');
$log->complete($key,11);autoCheck((new Log($path,'secret','plan-a'))->entries()[$key]['revision']===11,'Completion and revision survive reopening');
try{new Log($path,'secret','other-plan');throw new LogicException('Other plan accepted');}catch(RuntimeException){autoCheck(true,'Report is bound to exact plan');}
$raw=json_decode(file_get_contents($path),true);$raw['entries'][$key]['field']='tampered';file_put_contents($path,json_encode($raw));
try{new Log($path,'secret','plan-a');throw new LogicException('Tamper accepted');}catch(RuntimeException){autoCheck(true,'Modified report cannot authorize additions');}
unlink($path);
echo "$nAuto migration field selection/scope/write-ahead/signature/retry checks passed.\n";
