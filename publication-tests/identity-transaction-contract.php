<?php

declare( strict_types = 1 );
require __DIR__ . '/identity-mediawiki-boundary-contract.php';

use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Identity\MediaWikiIdentityConnection;
use ProfessionalWiki\SWS\Domain\Identity\{IdentityKind, LocalizedIdentity};
use ProfessionalWiki\SWS\Infrastructure\AfterCommitGraphDatabasePlugin;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\SWS\Domain\Page\{Page, PageId, PageProperties, PageSubjects};
use ProfessionalWiki\SWS\Domain\Schema\{SchemaName, SchemaReference};
use ProfessionalWiki\SWS\Domain\Subject\{Subject, SubjectId, SubjectLabel, SubjectMap, StatementList};

class TransactionHost extends IdentityMwHarness\Database {
 public array $callbacks = [];
 private array $callbackCounts = [];
 public function __construct(PDO $connection){$this->pdo=$connection;}
 public function startAtomic(string $name,string $flags=''):void{
  $this->callbackCounts[]=count($this->callbacks);parent::startAtomic($name,$flags);
 }
 public function endAtomic(string $name):void{parent::endAtomic($name);array_pop($this->callbackCounts);}
 public function cancelAtomic(string $name,mixed $scope=null):void{
  parent::cancelAtomic($name);$this->callbacks=array_slice($this->callbacks,0,array_pop($this->callbackCounts));
 }
 public function onTransactionCommitOrIdle(Closure $callback):void{
  if($this->pdo->inTransaction()){$this->callbacks[]=$callback;}else{$callback();}
 }
 public function commitRound():void{$this->pdo->commit();$callbacks=$this->callbacks;$this->callbacks=[];foreach($callbacks as$callback){$callback();}}
 public function abortRound():void{$this->pdo->rollBack();$this->callbacks=[];}
}
$checks=0;$results=[];
function txCheck(bool $ok,string $why):void{global$checks;$checks++;if(!$ok)throw new RuntimeException($why);}
foreach(['DELETE','WAL']as$mode){
 $path=tempnam(sys_get_temp_dir(),'sws-common-tx-');
 try{
  $pdo=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$pdo->exec('PRAGMA busy_timeout=100');
  txCheck(strtoupper($pdo->query('PRAGMA journal_mode='.$mode)->fetchColumn())===$mode,'Requested journal is active');
  $host=new TransactionHost($pdo);
  foreach(['sws_id_counter','sws_identity','sws_identity_alias']as$table){$pdo->exec(str_replace('/*_*/','mw_',file_get_contents(__DIR__.'/../sql/sqlite/'.$table.'.sql')));}
  foreach(IdentityKind::cases()as$kind){$host->insert('sws_id_counter',['swsic_kind'=>$kind->value,'swsic_value'=>0],__FILE__,['IGNORE']);}
  $pdo->exec("CREATE TABLE pages(id INTEGER PRIMARY KEY,text TEXT); INSERT INTO pages VALUES(1,'before')");
  $registry=new SqlIdentityRegistry(new MediaWikiIdentityConnection($host));
  $owner=hash('sha256','Nikolaos');
  $confirmed=$registry->reserveForOwner(IdentityKind::Entity,'pre-minted',$owner);
  txCheck(!$pdo->inTransaction()&&$confirmed->text==='E1','Pre-mint commits its own scope outside a caller transaction');
  $observer=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $pdo->beginTransaction();$pdo->query('SELECT * FROM pages')->fetchAll();
  $pending=$registry->reserveForOwner(IdentityKind::Entity,'automatic-will-fail',$owner);
  txCheck($pending->text==='E2'&&$pdo->inTransaction(),'Allocation joins an existing read transaction');
  txCheck((int)$observer->query("SELECT COUNT(*) FROM mw_sws_identity WHERE swsi_id='E2'")->fetchColumn()===0,'Another connection cannot observe an unconfirmed identity');
  $pdo->exec("UPDATE pages SET text='pending' WHERE id=1");
  $backend=new class implements GraphDatabasePlugin{
   public array $events=[];
   public function initialize():void{}
   public function savePage(Page $page):void{$this->events[]=['save',$page->getSubjects()->getMainSubject()?->id->text,$page->getSubjects()->getMainSubject()?->getLabel()?->text];}
   public function deletePage(PageId $id):void{$this->events[]=['delete',$id->id];}
  };
  $plugin=new AfterCommitGraphDatabasePlugin($backend,fn(Closure $callback)=>$host->onTransactionCommitOrIdle($callback));
  $entity=new Subject(new SubjectId($pending->text),new SubjectLabel('Vorläufig'),SchemaReference::local(new SchemaName('Person')),new StatementList(),identity:new LocalizedIdentity('de',['de'=>'Vorläufig']));
  $plugin->savePage(new Page(new PageId(1),new PageProperties(),new PageSubjects($entity,new SubjectMap())));
  $plugin->deletePage(new PageId(2));
  txCheck($backend->events===[],'No save/delete is sent to a graph before commit');
  $host->abortRound();
  txCheck($backend->events===[],'A failed page save publishes nothing');
  txCheck($registry->isReservedForOwner($confirmed,$owner),'Previously confirmed ID survives an aborted later page transaction');
  txCheck($registry->isReservedForOwner($pending,$owner)===false,'Aborted internal request no longer has a reservation');
  txCheck((int)$pdo->query("SELECT COUNT(*) FROM mw_sws_identity WHERE swsi_id='E2'")->fetchColumn()===0,'Never-issued automatic identity was discarded');
  $pdo->beginTransaction();$pdo->query('SELECT * FROM pages')->fetchAll();
  $success=$registry->reserveForOwner(IdentityKind::Entity,'automatic-success',$owner);
  txCheck($success->text==='E2','Only a never-published number can be assigned again');
  $pdo->exec("UPDATE pages SET text='saved' WHERE id=1");
  $entity=new Subject(new SubjectId($success->text),new SubjectLabel('Gesichert'),SchemaReference::local(new SchemaName('Person')),new StatementList(),identity:new LocalizedIdentity('de',['de'=>'Gesichert','en'=>'Saved']));
  $plugin->savePage(new Page(new PageId(1),new PageProperties(),new PageSubjects($entity,new SubjectMap())));
  $entity->setLabel(new SubjectLabel('Later mutation'));
  txCheck($backend->events===[],'Publication remains deferred');
  $host->commitRound();
  txCheck($backend->events===[['save','E2','Gesichert']],'Committed graph projection uses a frozen value set');
  txCheck($observer->query("SELECT text FROM pages WHERE id=1")->fetchColumn()==='saved'&&(int)$observer->query("SELECT COUNT(*) FROM mw_sws_identity WHERE swsi_id='E2'")->fetchColumn()===1,'Page and identity become visible together');
  // Partial cancellation must discard only the inner allocation and publication callbacks.
  $pdo->beginTransaction();$pdo->exec("UPDATE pages SET text='outer survives' WHERE id=1");
  $host->startAtomic('failed-inner','cancelable');
  $third=$registry->reserveForOwner(IdentityKind::Entity,'inner-failure',$owner);
  $plugin->deletePage(new PageId(9));
  $host->cancelAtomic('failed-inner');$host->commitRound();
  txCheck(count($backend->events)===1,'Cancelled inner scope does not publish a graph deletion');
  txCheck($pdo->query('SELECT text FROM pages WHERE id=1')->fetchColumn()==='outer survives','Cancelling an inner allocation preserves prior caller work');
  txCheck((int)$pdo->query("SELECT COUNT(*) FROM mw_sws_identity WHERE swsi_id='E3'")->fetchColumn()===0,'No partial inner identity remains');
  $results[]=['journal'=>$mode,'outcome'=>'pass','preMint'=>'E1','committedEntity'=>'E2','rollbackAndPostCommitPublication'=>true];
 }finally{$observer=null;$pdo=null;$host=null;@unlink($path);@unlink($path.'-wal');@unlink($path.'-shm');}
}
print json_encode(['checks'=>$checks,'cases'=>$results,'boundary'=>'Production MW adapter/registry/graph wrapper with real SQLite and controlled MediaWiki transaction callbacks. Not a full MediaWiki installation.'],JSON_PRETTY_PRINT)."\n";
