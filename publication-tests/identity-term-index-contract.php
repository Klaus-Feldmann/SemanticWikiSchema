<?php
declare(strict_types=1);
namespace UtfNormal { class Validator { public static function cleanUp(string $text):string{return \Normalizer::normalize($text,\Normalizer::FORM_C);} } }
namespace Wikimedia\Rdbms {interface IDatabase { public const ATOMIC_CANCELABLE="cancelable"; } }
namespace {
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabaseIdentityTermIndex as Index;
use ProfessionalWiki\SWS\Domain\Page\PageId;
final class TermSqliteDatabase implements \Wikimedia\Rdbms\IDatabase {
 public PDO $pdo;public int $writes=0;public bool $failInsert=false;
 public function __construct(){ $this->pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]); }
 public function newSelectQueryBuilder():TermBuilder{return new TermBuilder($this,'select');}
 public function newInsertQueryBuilder():TermBuilder{return new TermBuilder($this,'insert');}
 public function newDeleteQueryBuilder():TermBuilder{return new TermBuilder($this,'delete');}
 public function startAtomic($method):void{$this->pdo->exec('SAVEPOINT terms');}
 public function endAtomic($method):void{$this->pdo->exec('RELEASE SAVEPOINT terms');}
 public function cancelAtomic($method):void{$this->pdo->exec('ROLLBACK TO SAVEPOINT terms');$this->pdo->exec('RELEASE SAVEPOINT terms');}
}
final class TermBuilder {
 private string $table='';private array $where=[],$rows=[];
 public function __construct(private TermSqliteDatabase $db,private string $operation){}
 public function select($fields):self{return $this;}
 public function from($table):self{$this->table=$table;return $this;}
 public function insertInto($table):self{return $this->from($table);}
 public function deleteFrom($table):self{return $this->from($table);}
 public function where($conditions):self{$this->where=$conditions;return $this;}
 public function rows($rows):self{$this->rows=$rows;return $this;}
 public function caller($method):self{return $this;}
 private function conditions():string{return implode(' AND ',array_map(fn($key)=>$key.' = ?',array_keys($this->where)));}
 public function fetchResultSet():array{$q=$this->db->pdo->prepare('SELECT * FROM '.$this->table.' WHERE '.$this->conditions().' ORDER BY swst_language,swst_role');$q->execute(array_values($this->where));return $q->fetchAll(PDO::FETCH_OBJ);}
 public function execute():void {
  if($this->operation==='delete'){$q=$this->db->pdo->prepare('DELETE FROM '.$this->table.' WHERE '.$this->conditions());$q->execute(array_values($this->where));$this->db->writes++;return;}
  if($this->db->failInsert)throw new RuntimeException('Controlled insert failure');
  foreach($this->rows as$row){$q=$this->db->pdo->prepare('INSERT INTO '.$this->table.' ('.implode(',',array_keys($row)).') VALUES ('.implode(',',array_fill(0,count($row),'?')).')');$q->execute(array_values($row));$this->db->writes++;}
 }
}
$count=0;function termCheck(bool $ok,string $why):void{global$count;$count++;if(!$ok)throw new RuntimeException($why);}
$db=new TermSqliteDatabase();$sql=file_get_contents(__DIR__.'/../sql/sqlite/sws_search_term.sql');$db->pdo->exec(str_replace(['/*_*/','/*i*/'],'',$sql));
$index=new Index($db);$id=new PageId(5);
$schema=['labels'=>['de'=>'Person','en'=>'Human','fr'=>'Être humain'],'descriptions'=>['de'=>'Forschungsdaten','en'=>'Research data']];
$index->setSchema($id,$schema,'S1');
$all=$db->pdo->query('SELECT * FROM sws_search_term')->fetchAll(PDO::FETCH_ASSOC);
termCheck(count($all)===6,'All language labels, descriptions and explicit schema key are indexed');
$writes=$db->writes;$index->setSchema($id,$schema,'S1');termCheck($db->writes===$writes,'Unchanged terms cause zero SQL writes despite row ordering');
$schema['labels']['en']='Human being';$index->setSchema($id,$schema,'S1');termCheck($db->writes===$writes+2,'One changed language updates only its own term row');
$q=$db->pdo->prepare('SELECT swst_reference FROM sws_search_term WHERE swst_kind=? AND swst_key LIKE ?');$q->execute(['schema','human%']);termCheck($q->fetchColumn()==='S1','Translated label yields stable schema reference');
$q->execute(['schema','être%']);termCheck($q->fetchColumn()==='S1','Non-ASCII translated labels have Unicode-normalized search keys');
$before=$db->pdo->query('SELECT * FROM sws_search_term ORDER BY swst_language,swst_role')->fetchAll(PDO::FETCH_ASSOC);
$db->failInsert=true;$schema['labels']['en']='Failure';try{$index->setSchema($id,$schema,'S1');termCheck(false,'Should fail');}catch(RuntimeException $e){termCheck($e->getMessage()==='Controlled insert failure','Database failure propagates');}
termCheck($db->pdo->query('SELECT * FROM sws_search_term ORDER BY swst_language,swst_role')->fetchAll(PDO::FETCH_ASSOC)===$before,'Failed term update rolls back removed row');$db->failInsert=false;
$rows=Index::rows(5,'entity','E2',['de'=>'0','en'=>'','fr'=>'Émile'],['de'=>'Notiz']);termCheck(count($rows)===3&&$rows[0]['swst_key']==='0','Zero label retained, empty labels omitted');
$index->setSchema(new PageId(6),$schema,'S2');$index->removePage($id);termCheck((int)$db->pdo->query('SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=5')->fetchColumn()===0,'Deleting/unpublishing removes all page terms');termCheck((int)$db->pdo->query('SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=6')->fetchColumn()===6,'Other pages with same labels remain intact');
foreach(['mysql','postgres','sqlite']as$dialect){$text=file_get_contents(__DIR__.'/../sql/'.$dialect.'/sws_search_term.sql');termCheck(str_contains($text,'swst_lookup')&&str_contains($text,'PRIMARY KEY'),'Each supported SQL dialect supplies table and lookup index');}
echo "$count D5 identity term-index checks passed (actual writer with SQLite; controlled MediaWiki query builders).\n";
}
