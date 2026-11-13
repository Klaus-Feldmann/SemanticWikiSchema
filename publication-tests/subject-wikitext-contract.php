<?php
// Real SWS domain, renderer orchestration, API and metadata processing; host/parser are explicit stand-ins.
namespace Harness {
 final class State { public static $schema,$subjects,$revision,$output; public static bool $read=true,$published=true,$suppressed=false,$fail=false; public static array $parses=[]; }
 class Semantic {public function __construct(public array $values=[]){}public function importDataFrom($other){$this->values=array_merge($this->values,$other->values);}}
}
namespace MediaWiki\Title {class Title {public function getText(){return 'Test';}public function getPrefixedText(){return 'Test';}}}
namespace MediaWiki\User {class User {public function authorizeRead(...$args){return \Harness\State::$read;}}}
namespace MediaWiki\Revision {class RevisionRecord {
 public const DELETED_TEXT=1;public function userCan(...$args){return !\Harness\State::$suppressed;}public function getId(){return 42;}
 public function getContent($name){return new \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;}
}}
namespace Wikimedia\Rdbms {interface IDBAccessObject {public const READ_LATEST=1;}}
namespace MediaWiki\Parser {
 class ParserOptions {public static function newFromUser($user){return new self;}}
 class ParserOutput {
  public array $categories=[], $data=[], $links=[], $modules=[], $styles=[];
  public function __construct(public string $html=''){}
  public function getContentHolderText(){return $this->html;}public function getCategoryMap(){return $this->categories;}
  public function getExtensionData($name){return $this->data[$name]??null;}public function setExtensionData($name,$value){$this->data[$name]=$value;}
  public function addCategory($name,$sort){$this->categories[$name]=$sort;}
  public function mergeTrackingMetaDataFrom($source){$this->categories=array_merge($this->categories,$source->categories);$this->links=array_merge($this->links,$source->links);$this->data=array_merge($this->data,$source->data);}
  public function mergeHtmlMetaDataFrom($source){$this->modules=array_merge($this->modules,$source->modules);$this->styles=array_merge($this->styles,$source->styles);$this->data=array_merge($this->data,$source->data);}
  public function getModules(){return $this->modules;}public function getModuleStyles(){return $this->styles;}
 }
}
namespace MediaWiki {class MediaWikiServices {
 public static function getInstance(){return new self;}
 public function getTitleFactory(){return new class{public function newFromID($id){return $id===123?new \MediaWiki\Title\Title:null;}};}
 public function getRevisionLookup(){return new class{public function getRevisionByTitle(...$args){return \Harness\State::$revision;}};}
 public function getParserFactory(){return new class{public function create(){return new class{public function parse($source,$title,$options,$line,$clear,$rev){
  if(!\ProfessionalWiki\SWS\EntryPoints\Processing\FieldWikitextContext::isActive())throw new \RuntimeException('Missing recursion guard');
  \Harness\State::$parses[]=[$source,$title,$rev];if(\Harness\State::$fail)throw new \RuntimeException('Parser failure');
  return clone \Harness\State::$output;
 } };}};}
}}
namespace ProfessionalWiki\SWS\EntryPoints\Content {class SubjectContent {public function getPageSubjects(){return \Harness\State::$subjects;}}}
namespace ProfessionalWiki\SWS {class SWSExtension {
 public static function getInstance(){return new self;}
 public function getSubjectIdParser(){return new class{public function parse($id){return \ProfessionalWiki\SWS\Domain\Subject\SubjectId::isValidLocalId($id)?new \ProfessionalWiki\SWS\Domain\Subject\SubjectId($id):null;}};}
 public function getRevisionPolicy(){return new class{public function revisionIsReadableBy(...$args){return \Harness\State::$read;}public function publishedRevision($r){return \Harness\State::$published?$r:null;}};}
 public function getSchemaResolverFor($auth){return new class{public function getSchema($ref){return \Harness\State::$schema;}};}
}}
namespace {class ApiBase {
 public const PARAM_TYPE=1,PARAM_REQUIRED=2;public array $params=[],$result=[];public $cache;
 public function getMain(){return $this;}public function setCacheMode($mode){$this->cache=$mode;}
 public function extractRequestParams(){return $this->params;}public function getAuthority(){return new \MediaWiki\User\User;}public function getUser(){return new \MediaWiki\User\User;}
 public function dieWithError(...$args){throw new \RuntimeException('unavailable');}public function getResult(){return $this;}public function getModuleName(){return 'swsrenderwikitext';}
 public function addValue($unused,$module,$value){$this->result[$module]=$value;}
}}
namespace {
 require dirname(__DIR__).'/tests/standalone/autoload.php';
 use Harness\State as S;
 use ProfessionalWiki\SWS\Domain\Schema\{PropertyCore,PropertyDefinitions,PropertyName,Schema,SchemaName};
 use ProfessionalWiki\SWS\Domain\Schema\Property\{WikitextProperty,WikitextValueProperty,TextProperty};
 use ProfessionalWiki\SWS\Domain\Subject\{StatementList,SubjectMap};
 use ProfessionalWiki\SWS\Domain\Statement;
 use ProfessionalWiki\SWS\Domain\Value\StringValue;
 use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
 use ProfessionalWiki\SWS\Tests\Data\TestSubject;
 use ProfessionalWiki\SWS\EntryPoints\Processing\{UserWikitextRenderer,UserWikitextProcessor,FieldWikitextContext,ProcessingIncomplete};
 $n=0;function check($ok,$why){global $n;$n++;if(!$ok)throw new \RuntimeException($why);}
 $core=new PropertyCore('',false,null);
 foreach([[],['execute'=>false],['execute'=>true]]as $old){
  $p=WikitextProperty::fromPartialJson($core,$old+['wikitext'=>'{{Schema}}']);
  check($p->isSchemaExecution(),'Template always executes');check(!array_key_exists('execute',$p->toJson()),'No persisted execution switch');
 }
 $empty=WikitextProperty::fromPartialJson($core,['execute'=>false]);check(WikitextProperty::fromPartialJson($core,$empty->toJson())->isSchemaExecution(),'Legacy empty template roundtrip');
 $new=WikitextValueProperty::fromPartialJson($core,['multiple'=>true,'minLength'=>2]);check($new->getPropertyType()==='wikitextValue'&&!$new->isSchemaExecution(),'Separate editable type');check($new->getMinLength()===2&&$new->allowsMultipleValues(),'Text constraints retained');
 S::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Notiz'=>$new,'Vorlage'=>WikitextProperty::fromPartialJson(new PropertyCore('',false,null,fieldNotes:['type'=>'wikitext']),['wikitext'=>'{{Schema}}']),'Text'=>TextProperty::fromPartialJson(new PropertyCore('',false,null,fieldNotes:['type'=>'wikitext']),[])]),false);
 function subject($id,$source){return TestSubject::build(id:$id,schemaName:new SchemaName('Person'),statements:new StatementList([
  new Statement(new PropertyName('Notiz'),'wikitextValue',new StringValue(...$source)),
  new Statement(new PropertyName('Vorlage'),'wikitext',new StringValue('{{NeverSubjectSource}}')),
  new Statement(new PropertyName('Text'),'text',new StringValue('[[NeverParsed]]'))
 ]));}
 $main=subject('s11111111111111',['  [[Brünn]]\n','[https://example.org Quelle]']);$child=subject('s22222222222222',['[[Brno]]']);
 S::$subjects=new PageSubjects($main,new SubjectMap($child));S::$revision=new \MediaWiki\Revision\RevisionRecord;
 $rendered=new \MediaWiki\Parser\ParserOutput('<p><a>Brünn</a></p>');$rendered->categories=['Feld'=>'','Original'=>'Feldsort'];$rendered->links=['Vorlage:Test'];$rendered->modules=['ext.test'];$rendered->data['smwdata']=new \Harness\Semantic(['explicit']);S::$output=$rendered;
 $output=new \MediaWiki\Parser\ParserOutput('Article unchanged');$output->categories=['Original'=>'Quellsort'];$output->data['smwdata']=new \Harness\Semantic(['original']);
 $processor=new UserWikitextProcessor;$title=new \MediaWiki\Title\Title;$user=new \MediaWiki\User\User;
 $processor->apply($title,$output,S::$subjects,$user,static fn()=>S::$schema,42);
 check(count(S::$parses)===2,'All own subject values parsed, template and text untouched');check(S::$parses[0][0]===implode("\n\n",['  [[Brünn]]\n','[https://example.org Quelle]']),'Original whitespace preserved');check(S::$parses[0][2]===42,'Host revision passed');
 check($output->getContentHolderText()==='Article unchanged','Field HTML not appended to article');check($output->categories===['Original'=>'Quellsort','Feld'=>''],'Categories merged preserving original sort keys');check($output->links===['Vorlage:Test','Vorlage:Test'],'Template dependencies merged');check($output->data['smwdata']->values===['original','explicit','explicit'],'Explicit SMW merged with article annotations');check(!FieldWikitextContext::isActive(),'Recursion guard cleared');
 S::$fail=true;try{(new UserWikitextRenderer)->render($title,['x'],$user);check(false,'Parser exception hidden');}catch(\RuntimeException){check(!FieldWikitextContext::isActive(),'Guard restored after exception');}S::$fail=false;
 S::$output=new \MediaWiki\Parser\ParserOutput('<span class="scribunto-error">Error from another module</span>');$processor->apply($title,new \MediaWiki\Parser\ParserOutput,S::$subjects,$user,static fn()=>S::$schema);check(true,'Foreign module message does not block independent SWS metadata');
 S::$output=$rendered;$emptySubjects=new PageSubjects(subject('s11111111111111',[]),new SubjectMap);$fresh=new \MediaWiki\Parser\ParserOutput;$processor->apply($title,$fresh,$emptySubjects,$user,static fn()=>S::$schema);check($fresh->categories===[],'Removed field produces no stale categories');
 $reader=\ProfessionalWiki\SWS\Application\Semantic\FieldSnapshot::reader(S::$schema,$main,static fn()=>[]);check(count($reader->collectForAnnotations()['fields'])===1,'Raw wikitext excluded from automatic SMW scalar projection');check(count($reader->collect()['fields'])===2,'Wikitext available as source for template helpers');
 $api=new \ProfessionalWiki\SWS\EntryPoints\Api\ApiRenderWikitext;$api->params=['pageid'=>123,'subject'=>'s11111111111111','field'=>'Notiz'];$api->execute();
 check($api->cache==='private'&&!$api->isWriteMode(),'Read-only private parser endpoint');check($api->result['swsrenderwikitext']['html']==='<p><a>Brünn</a></p>','Saved field renders');check($api->result['swsrenderwikitext']['source']===implode("\n\n",$main->getStatements()->getStatement(new PropertyName('Notiz'))->getValue()->toScalars()),'Response tied to saved value');
 foreach([['field'=>'Vorlage'],['field'=>'Text'],['field'=>'Missing'],['subject'=>'invalid'],['pageid'=>0]]as $change){$api->params=$change+['pageid'=>123,'subject'=>'s11111111111111','field'=>'Notiz'];try{$api->execute();check(false,'Invalid field rendered');}catch(\RuntimeException $e){check($e->getMessage()==='unavailable','Non-user field rejected');}}
 $api->params=['pageid'=>123,'subject'=>'s22222222222222','field'=>'Notiz'];$api->execute();check($api->result['swsrenderwikitext']['source']==='[[Brno]]','Dependent subject uses own source');
 foreach(['read','published','suppressed']as $flag){S::$$flag=$flag==='suppressed';try{$api->execute();check(false,'Denied revision rendered');}catch(\RuntimeException $e){check($e->getMessage()==='unavailable','Unreadable/unpublished/suppressed revision rejected');}S::$$flag=$flag!=='suppressed';}
 $main->setFieldNotes(['Text'=>'[[Notiz]]']);
 $api->params=['pageid'=>123,'all'=>true];$api->execute();$entries=$api->result['swsrenderwikitext']['entries'];
 check(count($entries)===3,'One page batch contains two values and one author note');
 check($entries['["s11111111111111","Text","note"]']['source']==='[[Notiz]]','Batch note is distinct from ordinary text value');
 $api->params=['pageid'=>123,'subject'=>'s11111111111111','field'=>'Text','note'=>true];$api->execute();check($api->result['swsrenderwikitext']['source']==='[[Notiz]]','Direct note endpoint uses saved author source');
 S::$fail=true;$api->params=['pageid'=>123,'all'=>true];$api->execute();check(count(array_filter($api->result['swsrenderwikitext']['entries'],static fn($entry)=>$entry['error']??false))===3,'Batch parser failures isolated to their entries');S::$fail=false;
 $main->setFieldNotes(['Vorlage'=>'[[MustRemainHidden]]','Text'=>'[[Notiz]]']);$before=count(S::$parses);
 $processor->apply($title,new \MediaWiki\Parser\ParserOutput,S::$subjects,$user,static fn()=>S::$schema,42);
 check(count(S::$parses)-$before===3,'Dormant notes on a converted Template field never execute');
 echo "$n subject wikitext/template/parser/metadata/API checks passed (explicit MediaWiki parser stand-ins; not a live render test).\n";
}
