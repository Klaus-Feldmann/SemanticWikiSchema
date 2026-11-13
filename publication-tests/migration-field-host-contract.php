<?php
declare(strict_types=1);
require __DIR__.'/fixtures/MigrationFieldHost.php';require __DIR__.'/bootstrap.php';
use MediaWiki\MediaWikiServices as S;
use MediaWiki\Revision\RevisionRecord as R;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent as C;
use ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration\MediaWikiMigrationHost as Host;
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationFieldAdditionLog as Log,ReferenceRewriter as J};
$n=0;function fh(bool $ok,string $why):void{global$n;$n++;if(!$ok)throw new RuntimeException($why);}function refusesField(callable $f,string $why):void{try{$f();}catch(RuntimeException){fh(true,$why);return;}throw new RuntimeException('Accepted: '.$why);}
$source=(object)['formatVersion'=>2,'id'=>'S1','identityLanguage'=>'de','labels'=>(object)['de'=>'Person'],'descriptions'=>new stdClass(),'propertyDefinitions'=>(object)['Klassenzuordnung'=>(object)['type'=>'text','required'=>false,'externalProperty'=>(object)['source'=>'wikidata','id'=>'P31']]]];
$target=(object)['formatVersion'=>2,'id'=>'S2','identityLanguage'=>'de','labels'=>(object)['de'=>'Werk'],'descriptions'=>new stdClass(),'propertyDefinitions'=>new stdClass(),'opaque'=>(object)['keep'=>'unchanged']];
S::$revisions[101]=new R(10,101,new C(J::encode($source)));S::$revisions[102]=new R(20,102,new C(J::encode($target)));
$english=clone $target;$english->id='S3';$english->identityLanguage='en';$english->labels=(object)['en'=>'English'];S::$revisions[103]=new R(30,103,new C(J::encode($english)));
$actor=new class implements MediaWiki\Permissions\Authority {public bool $allowWrite=true;public function authorizeRead(mixed ...$a):bool{return true;}};
$host=new Host($actor,static fn($s)=>$s);$host->usePlan(['schemas'=>['Person'=>'S1','Werk'=>'S2','English'=>'S3']]);$path=tempnam(sys_get_temp_dir(),'sws-host-log-');unlink($path);$log=new Log($path,'secret','plan');$commits=0;$fail=false;$lines=[];
$host->enableFieldRepair($log,function()use(&$commits,&$fail):bool{$commits++;return !$fail;},function($line)use(&$lines):void{$lines[]=$line;});
$rc=new ReflectionClass($host);$add=$rc->getMethod('addMissingField');$recover=$rc->getMethod('recoverFieldAdditions');
$request=static fn($name)=>['schemaId'=>'S2','field'=>$name,'page'=>'Werk:Beispiel','caller'=>'Werk:Beispiel'];
$add->invoke($host,$request('Klassenzuordnung'));$data=json_decode(S::$revisions[102]->content->getText());
fh($data->propertyDefinitions->Klassenzuordnung->externalProperty->id==='P31','Actual host copies P31 definition');fh($data->opaque->keep==='unchanged'&&$data->descriptions instanceof stdClass,'Other schema data and object shapes preserved');fh($commits===1&&array_values($log->entries())[0]['status']==='completed','Report acknowledges only committed edit');fh(ProfessionalWiki\SWS\SWSExtension::$resets===1,'Schema lookup caches reset');
$add->invoke($host,$request('Unbekannt'));$data=json_decode(S::$revisions[102]->content->getText());fh($data->propertyDefinitions->Unbekannt->default===['SWS Migration Feld ergänzt'],'German fallback stored in schema only');fh(array_values($log->entries())[1]['needsReview'],'Fallback marked for review');
$add->invoke($host,array_replace($request('Unbekannt'),['schemaId'=>'S3']));$englishData=json_decode(S::$revisions[103]->content->getText());fh($englishData->propertyDefinitions->Unbekannt->default===['SWS migration field added'],'Copied placeholder is recreated in next schema language');$all=array_values($log->entries());fh($all[2]['needsReview']&&$all[2]['sourceSchema']===null,'Inherited placeholder still requires review');
refusesField(fn()=>$add->invoke($host,$request('Klassenzuordnung')),'Existing field protected');refusesField(fn()=>$add->invoke($host,array_replace($request('X'),['schemaId'=>'S99'])),'Outside-plan schema protected');
$fail=true;refusesField(fn()=>$add->invoke($host,$request('LostAck')),'Failed commit acknowledgement surfaced');$entries=array_values($log->entries());fh(end($entries)['status']==='pending','Failed acknowledgement stays pending');$writes=S::$writes;$fail=false;$recover->invoke($host);$entries=array_values($log->entries());fh(end($entries)['status']==='completed'&&S::$writes===$writes,'Lost acknowledgement recovers without duplicate revision');$recover->invoke($host);fh(S::$writes===$writes,'Repeated recovery is idempotent');
$actor->allowWrite=false;refusesField(fn()=>$add->invoke($host,$request('Denied')),'Edit permissions enforced');fh(S::$writes===$writes,'Denied edit preserves schema');$actor->allowWrite=true;
S::$beforeSave=static function($id):void{$r=S::$revisions[$id];S::$revisions[$id]=new R($r->getId()+5,$id,$r->content);};refusesField(fn()=>$add->invoke($host,$request('Concurrent')),'Concurrent edit refused by CAS');S::$beforeSave=null;unlink($path);
echo "$n actual migration host schema-addition/default/report/commit/recovery/permission/CAS checks passed with controlled MW saver.\n";
