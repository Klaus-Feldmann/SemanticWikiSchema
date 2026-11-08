<?php
// Isolated integration contract: real SWS hooks, stand-in MediaWiki services/parser/job execution.
namespace Harness {
 final class State {
  public static $schema,$subject,$old,$parser,$fail=false,$error=false;
  public static array $deferred=[],$queued=[],$dependencies=[],$ids=[],$conditions=[];
  public static int $refresh=0,$purges=0,$rebuilds=0,$logs=0;
 }
}
namespace {
 class Job {
  public const JOB_NO_EXPLICIT_TRX_ROUND = 1;
  protected $executionFlags = 0;
  protected $title,$params,$removeDuplicates=false;
  public function __construct($name,$title,$params){$this->title=$title;$this->params=$params;}
  public function parameters(){return $this->params;}
 }
 function wfMessage($key){return new class{public function escaped(){return 'Schema-Wikitext-Fehler';}};}
}
namespace MediaWiki\Title {
 class Title {
  public function __construct(public string $name='Regiomontanus',public int $latest=42,public string $model='wikitext'){}
  public static function newFromID($id){return new self('Page'.$id);}
  public static function newFromText($name){return new self($name);}
  public function getText(){return $this->name;}
  public function canExist(){return true;}
  public function getContentModel(){return $this->model;}
  public function getLatestRevID(){return $this->latest;}
  public function getArticleID(){return 123;}
 }
}
namespace MediaWiki\Revision {
 class SlotRecord {public const MAIN='main'; public function __construct(private bool $inherited){} public function isInherited(){return $this->inherited;} }
 class RevisionAccessException extends \Exception{}
 class RevisionRecord {
  public const RAW=1;
  public function __construct(public $main,public $neo=null,public bool $inherited=false){}
  public function getContent($role){return $role==='main'?$this->main:$this->neo;}
  public function hasSlot($role){return $role==='main'||$this->neo!==null;}
  public function getSlot($role,$audience){return new SlotRecord($this->inherited);}
 }
}
namespace MediaWiki\Parser {
 class Parser {
  public const OT_HTML=1;
  public function __construct(public $title,public int $revision=42,public bool $message=false,public int $type=1){$this->output=new class{
   public array $data=[];
   public function getExtensionData($key){return $this->data[$key]??null;}
   public function setExtensionData($key,$value){$this->data[$key]=$value;}
  };}
  public $output;
  public function getOutputType(){return $this->type;}
  public function getOptions(){return new class($this->message){public function __construct(private $message){}public function isMessage(){return $this->message;}};}
  public function getOutput(){return $this->output;}
  public function getTitle(){return $this->title;}
  public function getRevisionId(){return $this->revision;}
 }
}
namespace MediaWiki\Deferred {class DeferredUpdates{public static function addCallableUpdate($f){\Harness\State::$deferred[]=$f;}}}
namespace MediaWiki\Logger {class LoggerFactory{public static function getInstance($name){return new class{public function error(...$args){\Harness\State::$logs++;}};}}}
namespace MediaWiki\JobQueue\Jobs {class RefreshLinksJob{
 public function __construct(public $title,public $params){}
 public function run(){\Harness\State::$refresh++;return !\Harness\State::$fail;}
}}
namespace MediaWiki {
 class MediaWikiServices {
  public static function getInstance(){return new self;}
  public function hasService($name){return false;}
  public function getRevisionLookup(){return new class{public function getRevisionById($id){return \Harness\State::$old;}};}
  public function getJobQueueGroup(){return new class{public function push($job){\Harness\State::$queued[]=$job;}};}
  public function getWikiPageFactory(){return new class{public function newFromTitle($title){return new class($title){
   public function __construct(private $title){}public function doPurge(){\Harness\State::$purges++;}public function getTitle(){return $this->title;}
  };}};}
  public function getConnectionProvider(){return new class{public function getPrimaryDatabase(){return new class{
   public function expr($column,$op,$value){return $value;}
   public function newSelectQueryBuilder(){return new class{
    private int $after=0;
    public function select($v){return $this;}public function distinct(){return $this;}public function from($v){return $this;}
    public function where($v){\Harness\State::$conditions=$v;return $this;}
    public function andWhere($v){$this->after=$v;return $this;}public function orderBy($v){return $this;}
    public function limit($v){return $this;}public function caller($v){return $this;}
    public function fetchFieldValues(){return array_slice(array_values(array_filter(\Harness\State::$ids,fn($id)=>$id>$this->after)),0,100);}
   };}
  };}};}
 }
}
namespace ProfessionalWiki\SWS\EntryPoints {
 class ParserAuthority{public static function of($parser){return new \stdClass;}}
}
namespace ProfessionalWiki\SWS\Infrastructure {
 class ParserPageDependencyRecorder{public function __construct($parser){}public function recordDependencyOnSchema($schema){\Harness\State::$dependencies[]=$schema->name->getText();}}
}
namespace ProfessionalWiki\SWS\EntryPoints\Content {
 class SchemaContent{public function __construct(private string $text){}public function getText(){return $this->text;}}
 class SubjectContent{public function __construct(private $subject){}public function getPageSubjects(){return new class($this->subject){public function __construct(private $subject){}public function getMainSubject(){return $this->subject;}};}}
}
namespace ProfessionalWiki\SWS {
 class SWSExtension {
  public static function getInstance(){return new self;}
  public function newSubjectResolver($parser){return new class{public function resolveMainByTitle($title){return \Harness\State::$subject;}};}
  public function getSchemaResolverFor($authority){return $this->getSchemaResolver();}
  public function getSchemaResolver(){return new class{public function getSchema($ref){if(\Harness\State::$error)throw new \RuntimeException('lookup failed');return \Harness\State::$schema;}};}
  public function newHookPageRebuilder(){return new class{public function rebuild($title){\Harness\State::$rebuilds++;}};}
 }
}
namespace ProfessionalWiki\SWS\EntryPoints\Processing {
 // Scheduling contract only: the real worker is tested in the full MediaWiki fixtures.
 final class InternalPageProcessor {
  public function refresh($title): bool {
   \Harness\State::$refresh++;
   if(\Harness\State::$fail){return false;}
   \Harness\State::$purges++;\Harness\State::$rebuilds++;return true;
  }
 }
}
namespace {
 require dirname(__DIR__).'/tests/standalone/autoload.php';
 use Harness\State as S;
 use ProfessionalWiki\SWS\Domain\Schema\Property\WikitextProperty as WP;
 use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
 use ProfessionalWiki\SWS\Domain\Schema\Schema;
 use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
 use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
 use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
 use ProfessionalWiki\SWS\Application\SchemaWikitext;
 use ProfessionalWiki\SWS\EntryPoints\SchemaWikitextHooks as Hooks;
 use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
 use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
 use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
 use MediaWiki\Title\Title;
 use MediaWiki\Parser\Parser;
 use MediaWiki\Revision\RevisionRecord;
 $checks=0;function check($ok,$why){global $checks;$checks++;if(!$ok)throw new \RuntimeException($why);}
 function field($execute,$code){return WP::fromPartialJson(new PropertyCore('',false,null),['execute'=>$execute,'wikitext'=>$code]);}
 function resetState(){S::$deferred=[];S::$queued=[];S::$dependencies=[];S::$refresh=0;S::$purges=0;S::$rebuilds=0;S::$old=null;S::$fail=false;S::$error=false;}
 function drain(){while(S::$deferred){$f=array_shift(S::$deferred);$f();}}
 S::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Notiz'=>field(false,'{{NichtAusfuehren}}'),'SMW'=>field(true,'{{SWS}}'),'Weiter'=>field(true,'{{PAGENAME}}')]),false);
 S::$subject=new class{public function getSchemaReference(){return SchemaReference::local(new SchemaName('Person'));}};
 check(SchemaWikitext::programs(S::$schema)===['{{SWS}}','{{PAGENAME}}'],'Only explicit programs run, in schema order');
 check(SchemaWikitext::append('Seiteninhalt',[])==='Seiteninhalt','Empty program changes page');
 check(SchemaWikitext::append('Seiteninhalt',['{{SWS}}'])==="Seiteninhalt\n\n{{SWS}}",'Program is not appended');
 foreach([['execute'=>true,'wikitext'=>' '],['execute'=>'true','wikitext'=>'{{X}}'],['execute'=>true,'wikitext'=>[]]]as $bad){try{WP::fromPartialJson(new PropertyCore('',false,null),$bad);check(false,'Bad activation accepted');}catch(\InvalidArgumentException){check(true,'Invalid activation refused');}}
 $title=new Title;$source='Unveränderter Quelltext';$parser=new Parser($title);
 Hooks::onParserBeforeInternalParse($parser,$source,null);
 check(str_contains($source,'{{SWS}}'),'Schema program missing from host parse');
 check(!str_contains($source,'NichtAusfuehren'),'Inactive code executed');
 check(S::$dependencies===['Person'],'Schema dependency not recorded');
 $once=$source;Hooks::onParserBeforeInternalParse($parser,$source,null);check($source===$once,'Recursive parse duplicated program');
 foreach([new Parser($title,41),new Parser($title,42,true),new Parser($title,42,false,2),new Parser(new Title('Schema:Person',42,'SWSSchema'))]as $p){$text='Original';Hooks::onParserBeforeInternalParse($p,$text,null);check($text==='Original','Program ran in wrong context');}
 $savedSubject=S::$subject;S::$subject=null;$text='Original';Hooks::onParserBeforeInternalParse(new Parser($title),$text,null);check($text==='Original','Page without main subject changed');S::$subject=$savedSubject;
 S::$error=true;$text='Original';Hooks::onParserBeforeInternalParse(new Parser($title),$text,null);check(str_contains($text,'Schema-Wikitext-Fehler')&&S::$logs>0,'Lookup failure hidden');S::$error=false;
 resetState();$revision=new RevisionRecord('Seiteninhalt',new SubjectContent(S::$subject));$page=new class($title){public function __construct(private $title){}public function getTitle(){return $this->title;}};$tags=[];
 Hooks::onRevisionFromEditComplete($page,$revision,false,null,$tags);
 check(S::$refresh===0&&count(S::$deferred)===1,'Refresh ran before deferred/commit stage');drain();
 check(S::$refresh===1&&S::$purges===1&&S::$rebuilds===1,'Subject save did not refresh page and graph');
 check(S::$queued===[],'Unnecessary retry queued');
 resetState();$inherited=new RevisionRecord('Seiteninhalt',new SubjectContent(S::$subject),true);Hooks::onRevisionFromEditComplete($page,$inherited,false,null,$tags);check(S::$deferred===[],'Inherited subject slot caused another refresh');
 resetState();S::$old=$revision;$deleted=new RevisionRecord('Seiteninhalt',new SubjectContent(null));Hooks::onRevisionFromEditComplete($page,$deleted,1,null,$tags);drain();check(S::$refresh===1,'Last-subject removal did not refresh stale annotations');
 resetState();S::$old=$revision;Hooks::onRevisionFromEditComplete($page,new RevisionRecord('Seiteninhalt'),1,null,$tags);drain();check(S::$refresh===1,'Removed subject slot did not refresh stale annotations');
 resetState();S::$fail=true;Hooks::onRevisionFromEditComplete($page,$revision,false,null,$tags);drain();check(count(S::$queued)===1&&S::$queued[0]->parameters()['page']===true,'Failure did not queue bounded retry');check(S::$rebuilds===0,'Graph rebuilt after failed page refresh');
 resetState();S::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Notiz'=>field(false,'{{NichtAusfuehren}}')]),false);Hooks::onRevisionFromEditComplete($page,$revision,false,null,$tags);check(S::$deferred===[],'Ordinary subject save without program scheduled work');
 // Schema activation/removal must target indexed pages even if no template was ever in their source.
 $schemaPage=new class{public function getTitle(){return new Title('Person',42,'SWSSchema');}};
 $code=['propertyDefinitions'=>['SMW'=>['type'=>'wikitext','execute'=>true,'wikitext'=>'{{SWS}}']]];
 resetState();Hooks::onRevisionFromEditComplete($schemaPage,new RevisionRecord(new SchemaContent(json_encode($code))),false,null,$tags);drain();check(count(S::$queued)===1,'Schema activation did not schedule its subjects');
 S::$ids=range(1,101);$job=array_shift(S::$queued);check($job->run(),'Schema batch failed');check(count(S::$queued)===101,'Batch did not limit pages and schedule continuation');check(S::$conditions===['swss_schema'=>'Person','swss_is_main'=>1],'Batch is not scoped to the schema main subjects');
 $next=array_pop(S::$queued);check($next->parameters()['after']===100,'Cursor wrong');S::$queued=[];$next->run();check(count(S::$queued)===1,'Continuation did not process remaining page');
 resetState();S::$old=new RevisionRecord(new SchemaContent(json_encode($code)));Hooks::onRevisionFromEditComplete($schemaPage,new RevisionRecord(new SchemaContent('{"propertyDefinitions":{}}')),1,null,$tags);drain();check(count(S::$queued)===1,'Removing execution field did not schedule cleanup');
 resetState();S::$old=new RevisionRecord(new SchemaContent(json_encode($code)));Hooks::onRevisionFromEditComplete($schemaPage,new RevisionRecord(new SchemaContent(json_encode($code))),1,null,$tags);check(S::$deferred===[],'Unchanged schema program scheduled unnecessary work');
 $job=Hooks::refreshJob($title);check($job->params['recursive']===false&&$job->params['useRecursiveLinksUpdate']===false,'Refresh could recurse');check(!isset($job->params['rootJobTimestamp']),'Refresh can reuse inherited parser output');
 echo "$checks wikitext hook/storage/refresh contract checks passed (isolated MediaWiki stand-ins).\n";
}
