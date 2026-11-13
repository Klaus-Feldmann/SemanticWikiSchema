<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/fixtures/SqliteIdentityConnection.php';
use IdentityHarness\SqliteIdentityConnection;
use ProfessionalWiki\SWS\Application\IdentityMigration\{InventoryPlanner,MigrationCoordinator,MigrationHost,MigrationJournal,ReferenceRewriter,MigrationWriteScope};
use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
use ProfessionalWiki\SWS\Domain\Identity\IdentityKind;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
$n=0;function migrationCheck(bool $ok,string $message):void{global$n;$n++;if(!$ok)throw new RuntimeException($message);}
function migrationRefuses(Closure $f,string $message):void{try{$f();}catch(Throwable $e){migrationCheck(true,$message);return;}throw new RuntimeException('Not refused: '.$message);}
$db=new SqliteIdentityConnection(':memory:');
foreach(['sws_id_counter','sws_identity','sws_identity_alias']as$t){$db->pdo->exec(str_replace('/*_*/','test_',file_get_contents(__DIR__.'/../sql/sqlite/'.$t.'.sql')));}
foreach(IdentityKind::cases()as$kind){$db->insertIgnore('sws_id_counter',['swsic_kind'=>$kind->value,'swsic_value'=>0]);}
$registry=new SqlIdentityRegistry($db);$old='s11111111111111';$other='s11111111111112';
$snapshot=SourceSnapshot::recordAttempt(new SubjectId($old),7,20,[100=>30],'de','2026-11-12T00:00:00Z',[],[])->toJson();
$record=static fn($page,$kind,$name,$data)=>['pageId'=>$page,'revisionId'=>$page+20,'revisionSha1'=>'original-'.$page,'slot'=>$kind==='subjects'?'sws':'main','kind'=>$kind,'name'=>$name,'slots'=>['main'=>'unchanged article hash'],'json'=>ReferenceRewriter::encode($data)];
$inventory=['wiki'=>'aewi','language'=>'de','owner'=>hash('sha256','migration-operator'),'counters'=>['schema'=>0,'entity'=>0,'relation'=>0],'occupiedSchemaTitles'=>['Person','S1'],
 'records'=>[
 $record(100,'schema','Person',(object)['description'=>'Personen','propertyDefinitions'=>(object)['Kennt'=>(object)['type'=>'relation','targetSchemas'=>['S1'],'default'=>[(object)['id'=>'r11111111111111','target'=>$other]]],'Text'=>(object)['type'=>'text','default'=>'S1']]]),
 $record(101,'schema','S1',(object)['propertyDefinitions'=>(object)['Zurück'=>(object)['type'=>'relation','targetSchema'=>'Person']]]),
 $record(7,'subjects','Kurt Gödel',(object)['mainSubject'=>$old,'subjects'=>(object)[
  $old=>(object)['schema'=>'Person','label'=>'Gödel','statements'=>(object)['Kennt'=>(object)['propertyType'=>'relation','value'=>[(object)['id'=>'r11111111111111','target'=>$other]]],'Text'=>(object)['propertyType'=>'text','value'=>[$old.' S1 Person']]],'fieldNotes'=>(object)['Ort'=>'[[Brünn]]'],'sourceSnapshot'=>json_decode(json_encode($snapshot))],
  $other=>(object)['schema'=>'S1','statements'=>(object)[],'opaque'=>(object)['target'=>$old]],
 ]]),
 $record(102,'mapping','Research',(object)['version'=>1,'schemas'=>(object)['Person'=>(object)['subject'=>(object)['class'=>'https://example.test/Human']],'S1'=>(object)['subject'=>(object)['class'=>'https://example.test/Other']]]]),
 $record(103,'layout','Person',(object)['schema'=>'Person','type'=>'table','settings'=>(object)['text'=>'S1']]),
 ]];
$normalizer=static fn(string $s):string=>ucfirst(str_replace('_',' ',$s));$planner=new InventoryPlanner($registry,$normalizer);
$before=$db->pdo->query('SELECT * FROM test_sws_id_counter ORDER BY swsic_kind')->fetchAll(PDO::FETCH_ASSOC);
$plan=$planner->plan($inventory);migrationCheck($plan===$planner->plan($inventory),'Preview is deterministic');
migrationCheck($before===$db->pdo->query('SELECT * FROM test_sws_id_counter ORDER BY swsic_kind')->fetchAll(PDO::FETCH_ASSOC)&&(int)$db->pdo->query('SELECT COUNT(*) FROM test_sws_identity')->fetchColumn()===0,'Preview writes no counters/reservations');
migrationCheck($plan['schemas']===['Person'=>'S2','S1'=>'S3'],'Occupied legacy title S1 is skipped instead of reinterpreted');
migrationCheck($plan['entities'][$old]==='E1'&&$plan['entities'][$other]==='E2','Legacy entity allocation is stable and typed');
$ops=$plan['operations'];migrationCheck($ops[0]['action']==='create-schema'&&$ops[1]['action']==='create-schema'&&end($ops)['action']==='retire-schema','All ID schemas are created before references and old-page redirects');
$a=ReferenceRewriter::object($ops[0]['json']);$b=ReferenceRewriter::object($ops[1]['json']);
migrationCheck($a->propertyDefinitions->Kennt->targetSchemas[0]->id==='S3'&&$b->propertyDefinitions->{'Zurück'}->targetSchema->id==='S2','Cyclic schema dependencies use fully allocated IDs');
migrationCheck($a->propertyDefinitions->Kennt->default[0]->target==='E2'&&$a->propertyDefinitions->Text->default==='S1','Only relation defaults are rewritten');
$entityOp=array_values(array_filter($ops,fn($op)=>($op['record']['kind']??null)==='subjects'))[0];$out=ReferenceRewriter::object($entityOp['json']);
migrationCheck($out->mainSubject==='E1'&&$out->subjects->E1->schema->id==='S2','Main entity and schema use explicit IDs');
migrationCheck($out->subjects->E1->statements->Kennt->value[0]->target==='E2'&&$out->subjects->E1->statements->Kennt->value[0]->id==='r11111111111111','Relation target changes but relation identity is preserved');
migrationCheck($out->subjects->E1->statements->Text->value[0]===$old.' S1 Person'&&$out->subjects->E1->fieldNotes->Ort==='[[Brünn]]'&&$out->subjects->E2->opaque->target===$old,'Research text, notes and unknown payloads stay unchanged');
$rewritten=json_decode(json_encode($out->subjects->E1->sourceSnapshot),true);$expected=$snapshot;$expected['subjectId']='E1';
migrationCheck($rewritten===$expected,'Stored source payload changes only its owning entity ID; timestamps and provenance remain');
migrationCheck(SourceSnapshot::fromJson($rewritten)->toJson()===$rewritten,'Rekeyed source snapshot remains valid');
$rw=new ReferenceRewriter($plan['schemas'],$plan['entities'],'aewi','de',$normalizer);
migrationCheck($rw->entityReference('remote:'.$old)==='remote:'.$old,'Foreign entity reference is never localized');
migrationCheck($rw->schemaReference((object)['source'=>'remote','name'=>'Person'])->source==='remote','Foreign schema reference retains its source');
migrationCheck($rw->schemaReference((object)['id'=>'S1'])->id==='S1','Explicit S1 is not the old schema named S1');
$bad=$inventory;$bad['records'][]=$record(8,'subjects','Duplicate',ReferenceRewriter::object($inventory['records'][2]['json']));migrationRefuses(fn()=>$planner->plan($bad),'Multiple hosting pages are refused before allocating');
$bad=$inventory;$x=ReferenceRewriter::object($bad['records'][0]['json']);$x->propertyDefinitions->Kennt->targetSchemas=['Missing'];$bad['records'][0]['json']=ReferenceRewriter::encode($x);migrationRefuses(fn()=>$planner->plan($bad),'Unresolved local schema dependencies stop preview rather than inventing schema data');
class ContractMigrationHost implements MigrationHost {
 public array $created=[],$current=[],$history=[];public int $writes=0,$rebuilds=0;public bool $crash=false,$rebuildFailure=false;
 public function __construct(private array $snapshot){foreach($snapshot['records']as$r){$this->current[$r['pageId']]=$r['json'];$this->history[$r['pageId']]=[$r['json']];}}
 public function inventory():array{return $this->snapshot;}
 public function preflight(array $plan):void{}
 public function createSchema(string $id,string $json):void{if(isset($this->created[$id])&&$this->created[$id]!==$json)throw new RuntimeException('Conflict');if(!isset($this->created[$id])){$this->created[$id]=$json;$this->writes++;}}
 public function updateRecord(array $r,string $json,bool $redirect=false):void{if($this->current[$r['pageId']]===$json)return;if($this->current[$r['pageId']]!==$r['json'])throw new RuntimeException('CAS conflict');$this->current[$r['pageId']]=$json;$this->history[$r['pageId']][]=$json;$this->writes++;if($this->crash){$this->crash=false;throw new RuntimeException('Lost acknowledgement');}}
 public function rebuild():void{$this->rebuilds++;if($this->rebuildFailure){$this->rebuildFailure=false;throw new RuntimeException('Projection failed');}}
}
$host=new ContractMigrationHost($inventory);$coordinator=new MigrationCoordinator($registry,$host);$state=[];$save=function($s)use(&$state){$state=$s;};$host->crash=true;
migrationRefuses(fn()=>$coordinator->apply($plan,$state,$save),'Interrupted write leaves resumable checkpoint');
migrationCheck(!MigrationWriteScope::active(),'Migration write scope always closes on failure');
$countIds=(int)$db->pdo->query('SELECT COUNT(*) FROM test_sws_identity')->fetchColumn();$state=$coordinator->apply($plan,$state,$save);
migrationCheck($state['rebuilt']&&$state['operation']===count($ops),'Resume completes all operations and projections');
migrationCheck((int)$db->pdo->query('SELECT COUNT(*) FROM test_sws_identity')->fetchColumn()===$countIds,'Resume does not allocate replacement identities');
$writes=$host->writes;$coordinator->apply($plan,$state,$save);migrationCheck($host->writes===$writes&&$host->rebuilds===1,'Completed replay performs no page or projection writes');
migrationCheck($host->history[7][0]===$inventory['records'][2]['json']&&$host->history[7][1]===$entityOp['json'],'Original revision content remains intact in the test-copy history');
$path=tempnam(sys_get_temp_dir(),'sws-d6-journal-');unlink($path);$journal=new MigrationJournal($path,'secret');$journal->write($plan,$state);migrationCheck($journal->read()['plan']===$plan,'Signed journal preserves source documents and checkpoint');
migrationRefuses(fn()=>new MigrationJournal($path,'secret'),'Concurrent journal writer is refused');
$originalJournal=file_get_contents($path);$journal->checkpoint($state);migrationCheck($journal->read()['state']===$state&&file_get_contents($path)===$originalJournal,'Progress advances without rewriting original source documents');
$data=json_decode(file_get_contents($path),true);$data['state']['operation']=999;file_put_contents($path,json_encode($data));migrationRefuses(fn()=>$journal->read(),'Modified journal cannot authorize migration');unset($journal);unlink($path);unlink($path.'.lock');unlink($path.'.state');
echo "$n D6 deterministic migration/reference/source-preservation/collision/journal/resume checks passed (real SQLite registry; controlled revision host).\n";
