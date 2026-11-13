<?php

declare( strict_types = 1 );
require __DIR__.'/identity-transaction-contract.php';

use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\SWS\Persistence\Identity\SqlIdentityRegistry;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Identity\MediaWikiIdentityConnection;
use ProfessionalWiki\SWS\Domain\Identity\IdentityKind;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException;

class HostingDatabase extends TransactionHost {
 public function condition(array $where):string{
  $parts=[];foreach($where as$key=>$value){
   $parts[]=is_array($value)?$key.' IN ('.implode(',',array_map(fn($v)=>$this->addQuotes((string)$v),$value)).')':$key.'='.$this->addQuotes((string)$value);
  }return implode(' AND ',$parts);
 }
 public function selectFieldValues(string $table,string $field,array $where,string $caller):array{return $this->pdo->query('SELECT '.$field.' FROM '.$this->tableName($table).' WHERE '.$this->condition($where))->fetchAll(PDO::FETCH_COLUMN);}
 public function selectField(string $table,string $field,array $where,string $caller,array $options=[]):mixed{return $this->pdo->query('SELECT '.$field.' FROM '.$this->tableName($table).' WHERE '.$this->condition($where))->fetchColumn();}
 public function update(string $table,array $set,array $where,string $caller):void{
  $parts=[];foreach($set as$key=>$value){$parts[]=$key.'='.$this->addQuotes((string)$value);}
  $this->pdo->exec('UPDATE '.$this->tableName($table).' SET '.implode(',',$parts).' WHERE '.$this->condition($where));
 }
 public function delete(string $table,array $where,string $caller):void{$this->pdo->exec('DELETE FROM '.$this->tableName($table).' WHERE '.$this->condition($where));}
 public function newSelectQueryBuilder():HostingQuery{return new HostingQuery($this,'select');}
 public function newDeleteQueryBuilder():HostingQuery{return new HostingQuery($this,'delete');}
 public function newInsertQueryBuilder():HostingQuery{return new HostingQuery($this,'insert');}
}
class HostingQuery {
 private string $table='';private array $where=[];private string $field='';private array $rows=[];
 public function __construct(private HostingDatabase $db,private string $kind){}
 public function select(string $field):self{$this->field=$field;return $this;}
 public function from(string $table):self{$this->table=$table;return $this;}
 public function where(array $where):self{$this->where=$where;return $this;}
 public function caller(string $caller):self{return $this;}
 public function deleteFrom(string $table):self{$this->table=$table;return $this;}
 public function insertInto(string $table):self{$this->table=$table;return $this;}
 public function rows(array $rows):self{$this->rows=$rows;return $this;}
 public function fetchFieldValues():array{return $this->db->selectFieldValues($this->table,$this->field,$this->where,__FILE__);}
 public function execute():void{
  if($this->kind==='delete'){$this->db->delete($this->table,$this->where,__FILE__);return;}
  foreach($this->rows as$row){$this->db->pdo->exec('INSERT INTO '.$this->db->tableName($this->table).' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_map(fn($v)=>$this->db->addQuotes((string)$v),$row)).')');}
 }
}
$n=0;function hostCheck(bool $ok,string $why):void{global$n;$n++;if(!$ok)throw new RuntimeException($why);}
$db=new HostingDatabase(new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]));
foreach(['sws_id_counter','sws_identity','sws_identity_alias','sws_identity_host','sws_subject_page']as$table){$db->pdo->exec(str_replace('/*_*/','mw_',file_get_contents(__DIR__.'/../sql/sqlite/'.$table.'.sql')));}
foreach(IdentityKind::cases()as$kind){$db->insert('sws_id_counter',['swsic_kind'=>$kind->value,'swsic_value'=>0],__FILE__,['IGNORE']);}
$registry=new SqlIdentityRegistry(new MediaWikiIdentityConnection($db));$owner=hash('sha256','Nikolaos');
$id=$registry->reserveForOwner(IdentityKind::Entity,'new-one',$owner);$index=new DatabaseSubjectPageIndex($db,enforceSequentialIds:true);
hostCheck($registry->canCreate($id,$owner),'Unused owned reservation may be created');
$index->setSubjectsOfPage(new PageId(1),[$id->text]);
hostCheck($db->selectField('sws_identity_host','swsih_page',['swsih_id'=>$id->text],__FILE__)==1,'Production page index records unique host');
hostCheck(!$registry->canCreate($id,$owner)&&$registry->isReservedForOwner($id,$owner),'Used state remains an issued reservation');
$index->setSubjectsOfPage(new PageId(1),[$id->text]);
hostCheck($db->pdo->query('SELECT COUNT(*) FROM mw_sws_identity_host')->fetchColumn()==1,'Resaving does not duplicate a host');
try{$index->setSubjectsOfPage(new PageId(2),[$id->text]);throw new RuntimeException('Duplicate host accepted');}catch(SubjectIdInUseException $error){hostCheck(true,'Same E ID on another page is refused');}
hostCheck(!$db->pdo->inTransaction()&&$db->pdo->query('SELECT COUNT(*) FROM mw_sws_subject_page WHERE swssp_page_id=2')->fetchColumn()==0,'Failed host claim leaves no partial page index');
$index->setSubjectsOfPage(new PageId(1),[]);$index->setSubjectsOfPage(new PageId(2),[$id->text]);
hostCheck($db->selectField('sws_identity_host','swsih_page',['swsih_id'=>$id->text],__FILE__)==2,'Authorized move can carry an existing identity to its new page');
$index->removePage(new PageId(2));
hostCheck(!$registry->canCreate($id,$owner),'Deletion never makes an issued ID available for fresh creation');
$index->setSubjectsOfPage(new PageId(3),[$id->text]);
hostCheck($db->selectField('sws_identity_host','swsih_page',['swsih_id'=>$id->text],__FILE__)==3,'Revision restoration can restore the original identity');
try{$index->setSubjectsOfPage(new PageId(4),['E999']);throw new LogicException('Unreserved ID accepted');}catch(RuntimeException $error){hostCheck($error->getMessage()!=='Unreserved ID accepted','Unreserved E ID rejected');}
hostCheck($db->pdo->query('SELECT COUNT(*) FROM mw_sws_subject_page WHERE swssp_page_id=4')->fetchColumn()==0,'Invalid identity cannot leave an index row');
$id2=$registry->reserveForOwner(IdentityKind::Entity,'new-two',$owner);
$db->pdo->beginTransaction();$index->setSubjectsOfPage(new PageId(5),[$id2->text]);$db->abortRound();
hostCheck($registry->canCreate($id2,$owner)&&$db->pdo->query("SELECT COUNT(*) FROM mw_sws_identity_host WHERE swsih_id='E2'")->fetchColumn()==0,'Page failure rolls back host and used state but keeps a confirmed pre-mint');
$index->setSubjectsOfPage(new PageId(6),['s11111111111111']);$index->setSubjectsOfPage(new PageId(7),['s11111111111111']);
hostCheck($db->pdo->query("SELECT COUNT(*) FROM mw_sws_subject_page WHERE swssp_subject_id='s11111111111111'")->fetchColumn()==2,'Legacy cross-page behavior is retained');
print "$n D2 unique hosting, move, restore and issued-ID checks passed (production index with real SQLite and controlled MW query boundary).\n";
