<?php
declare(strict_types=1);
namespace Wikimedia\Rdbms {
 interface IReadableDatabase {} interface IConnectionProvider {} interface IResultWrapper extends \IteratorAggregate {}
 interface IExpression {public const LIKE='LIKE';}
 class LikeValue {public string $pattern;public function __construct(...$parts){$this->pattern=implode('',$parts);}}
 class SearchExpression implements IExpression {
  public function __construct(public string $sql,private \PDO $pdo){}
  public function or($field,$operator,$value):self{$v=$value instanceof LikeValue?$value->pattern:$value;$this->sql='('.$this->sql.' OR '.$field.' '.$operator.' '.$this->pdo->quote((string)$v).')';return $this;}
  public function __toString():string{return $this->sql;}
 }
}
namespace MediaWiki\Title {
 class Title {public function __construct(public int $id,public int $ns,public string $name){}public function getPrefixedText():string{return $this->name;}}
 class TitleValue {public function __construct(public int $ns,private string $name){}public function getText():string{return str_replace('_',' ',$this->name);}}
 class TitleFactory {public function newFromRow($r):Title{return new Title((int)$r->page_id,(int)$r->page_namespace,$r->page_title);}public function newFromLinkTarget($t):Title{return new Title(0,$t->ns,$t->getText());}}
}
namespace {
use ProfessionalWiki\SWS\Persistence\MediaWiki\DatabaseIdentityTermIndex as Index;
use ProfessionalWiki\SWS\Domain\Page\PageId;
require __DIR__.'/identity-term-index-contract.php';
class SearchEngine {}
final class SearchDatabase implements \Wikimedia\Rdbms\IDatabase,\Wikimedia\Rdbms\IReadableDatabase {
 public function __construct(public PDO $pdo){}
 public function select($table,$fields,$conditions,$caller,$options):\Wikimedia\Rdbms\IResultWrapper {
  $builder=$this->newSelectQueryBuilder()->select($fields)->from($table);
  foreach($conditions as$key=>$value){$builder->andWhere(is_int($key)?$value:[$key=>$value]);}
  return $builder->orderBy($options['ORDER BY'])->limit($options['LIMIT'])->offset($options['OFFSET']??0)->fetchResultSet();
 }
 public function newSelectQueryBuilder():SearchBuilder{return new SearchBuilder($this);}
 public function anyString():string{return '%';}
 public function addQuotes($value):string{return $this->pdo->quote((string)$value);}
 public function expr($field,$operator,$value):\Wikimedia\Rdbms\SearchExpression{$value=$value instanceof \Wikimedia\Rdbms\LikeValue?$value->pattern:$value;return new \Wikimedia\Rdbms\SearchExpression($field.' '.$operator.' '.$this->addQuotes($value),$this->pdo);}
}
final class SearchBuilder {
 private string $fields='*',$table='';private array $joins=[],$conditions=[];private string $order='';private int $limit=0,$offset=0;private bool $distinct=false;
 public function __construct(private SearchDatabase $db){}
 public function select($fields):self{$this->fields=is_array($fields)?implode(',',$fields):$fields;return $this;}
 public function distinct():self{$this->distinct=true;return $this;}
 public function from($table,$alias=null):self{$this->table=$table.($alias?' '.$alias:'');return $this;}
 public function join($table,$alias,$on):self{$this->joins[]=' JOIN '.$table.($alias?' '.$alias:'').' ON '.$on;return $this;}
 public function leftJoin($table,$alias,$on):self{$this->joins[]=' LEFT JOIN '.$table.($alias?' '.$alias:'').' ON '.$on;return $this;}
 public function where($conditions):self {
  if(!is_array($conditions)){$this->conditions[]=(string)$conditions;return $this;}
  foreach($conditions as$key=>$value){$this->conditions[]=is_array($value)?$key.' IN ('.implode(',',array_map($this->db->addQuotes(...),$value)).')':$key.' = '.$this->db->addQuotes($value);}
  return $this;
 }
 public function andWhere($conditions):self{return $this->where($conditions);}
 public function orderBy($fields):self{$this->order=is_array($fields)?implode(',',$fields):$fields;return $this;}
 public function limit($limit):self{$this->limit=$limit;return $this;}
 public function offset($offset):self{$this->offset=$offset;return $this;}
 public function caller($method):self{return $this;}
 public function getSQL():string{return 'SELECT '.($this->distinct?'DISTINCT ':'').$this->fields.' FROM '.$this->table.implode('',$this->joins).($this->conditions?' WHERE '.implode(' AND ',array_map(fn($s)=>'('.$s.')',$this->conditions)):'').($this->order?' ORDER BY '.$this->order:'').($this->limit?' LIMIT '.$this->limit.' OFFSET '.$this->offset:'');}
 public function fetchResultSet():\Wikimedia\Rdbms\IResultWrapper{$rows=$this->db->pdo->query($this->getSQL())->fetchAll(PDO::FETCH_OBJ);return new class($rows)implements \Wikimedia\Rdbms\IResultWrapper{public function __construct(private array $rows){}public function getIterator():Traversable{return new ArrayIterator($this->rows);}public function numRows():int{return count($this->rows);}};}
}
$pdo=$db->pdo;$pdo->exec('CREATE TABLE page(page_id INTEGER PRIMARY KEY,page_namespace INTEGER,page_title TEXT,page_is_redirect INTEGER NOT NULL DEFAULT 0)');
$pdo->exec('CREATE TABLE sws_subject(swss_page_id BIGINT,swss_subject_id TEXT,swss_display_name TEXT,swss_display_name_key TEXT,swss_dependent INTEGER,swss_schema TEXT)');
foreach([[1,0,'Visible'],[2,0,'Duplicate'],[3,0,'Private'],[4,0,'Dependent'],[10,7474,'S10'],[11,7474,'PrivateSchema']]as$r){$q=$pdo->prepare('INSERT INTO page(page_id,page_namespace,page_title) VALUES(?,?,?)');$q->execute($r);}
foreach([[1,'E1','Mensch','mensch',0,'S1'],[2,'E1','Mensch','mensch',0,'S1'],[3,'E2','Mensch','mensch',0,'S1'],[4,'E3','Abhängig','abhängig',1,'S1']]as$r){$q=$pdo->prepare('INSERT INTO sws_subject VALUES(?,?,?,?,?,?)');$q->execute($r);}
foreach([[1,'E1'],[2,'E1'],[3,'E2'],[4,'E3']]as[$page,$id]){foreach(Index::rows($page,'entity',$id,['en'=>'Human','de'=>'Mensch','fr'=>'Human'],[])as$r){$q=$pdo->prepare('INSERT INTO sws_search_term VALUES(?,?,?,?,?,?)');$q->execute(array_values($r));}}
$index->setSchema(new PageId(10),['labels'=>['en'=>'Human schema'],'descriptions'=>['de'=>'Forschungsbestand']],'S10');
$index->setSchema(new PageId(11),['labels'=>['en'=>'Human schema']],'PrivateSchema');
$searchDb=new SearchDatabase($pdo);$provider=new class($searchDb)implements \Wikimedia\Rdbms\IConnectionProvider {public function __construct(private $db){}public function getReplicaDatabase(){return $this->db;}};
$auth=new class implements \ProfessionalWiki\SWS\Application\PageReadAuthorizer {
 public function authorizeReadByPageId(\ProfessionalWiki\SWS\Domain\Page\PageId $id):bool{return $id->id!==3;}
 public function authorizeReadByPageTitle(\MediaWiki\Title\Title $title):bool{return !in_array($title->name,['Private','PrivateSchema'],true);}
};
$lookup=new \ProfessionalWiki\SWS\Persistence\MediaWiki\DatabaseSubjectLabelLookup($provider,$auth,new \MediaWiki\Title\TitleFactory());
$found=$lookup->getSubjectLabelsMatching('Human',10,null);termCheck(count($found)===1&&$found[0]->id==='E1','Actual translated-label query deduplicates languages, duplicate hosts, private and dependent entities');
termCheck(count($lookup->getSubjectLabelsMatching('E1',10,['S1']))===1,'Explicit ID prefix remains searchable with schema filter');
termCheck($lookup->getSubjectLabelsMatching('Human',10,['S2'])===[],'Translated search respects selected schema');
$schemas=new \ProfessionalWiki\SWS\Persistence\MediaWiki\DatabaseSchemaNameLookup($searchDb,new SearchEngine(),$auth,new \MediaWiki\Title\TitleFactory());
$found=$schemas->getSchemaNamesMatching('Human',10);termCheck(count($found)===1&&$found[0]->getText()==='S10','Actual schema query searches translated labels and filters restricted schemas');
termCheck($schemas->getSchemaNamesMatching('Forschungs',10)[0]->getText()==='S10','Schema description is searchable without scanning revision content');
$pdo->exec("INSERT INTO page VALUES(5,7474,'Human_old',1)");
$index->setSchema(new PageId(5),['labels'=>['en'=>'Human schema']],'Human_old');
$found=$schemas->getSchemaNamesMatching('Human',10);termCheck(count($found)===1&&$found[0]->getText()==='S10','Redirect excluded even with stale translated search terms');
termCheck(array_map(fn($t)=>$t->getText(),$schemas->getSchemaNamesMatching('',10))===['S10'],'Unfiltered picker excludes redirects and unreadable pages');
termCheck(array_keys(iterator_to_array($schemas->getReadableSchemaNames()))===[10],'Schema table cursor excludes redirects before JSON lookup');
termCheck(iterator_to_array($schemas->getReadableSchemaNames(10))===[],'Schema cursor terminates after last readable definition');
echo "D5 actual entity/schema search queries passed (real SQLite; controlled MW builders and rights).\n";
}
