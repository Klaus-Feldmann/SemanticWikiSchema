<?php
// Real DataViewLookup + assembler, isolated MediaWiki/source adapters. Not a live MW test.
namespace MediaWiki\Context { interface IContextSource { public function getAuthority(); } }
namespace MediaWiki\Permissions { interface Authority { public function authorizeRead($right,$title); } }
namespace MediaWiki {
 class MediaWikiServices {
  public static function getInstance(){return new self();}
  public function getMainConfig(){return new class {public function get($key){return $key==='SWSSourceDataMode'?'cache':null;}};}
  public function getPageProps(){return new class { public function getProperties($title,$name){
   if(\Harness\State::$pagePropError)throw new \RuntimeException('Page props unavailable');
   return \Harness\State::$shortdesc===null?[]:[$title->getArticleID()=>\Harness\State::$shortdesc];
  }};}
 }
}
namespace MediaWiki\Title {
 class Title {
  public function __construct(public int $id=42,public string $text='Beispiel'){}
  public function getPageLanguage(){return new class {public function getCode(){return 'de';}};}
  public function getArticleID(){return $this->id;}
  public function getText(){return $this->text;}
  public function getPrefixedText(){return $this->text;}
  public function getFullURL(){return 'https://example.org/wiki/'.rawurlencode($this->text);}
  public static function newFromID($id){return $id===999?null:new self($id);}
  public static function newFromText($text){return new self(42,$text);}
 }
}
namespace ProfessionalWiki\SWS {
 class SWSExtension {
  public static $instance;
  public static function getInstance(){return self::$instance;}
  public function getRdfNamespaces(){return new \ProfessionalWiki\SWS\Domain\Rdf\RdfNamespaces('https://example.org');}
  public function getRdfSerializer(){return new \ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer($this->getRdfNamespaces()->prefixMap(),new \Psr\Log\NullLogger());}
  public function newPageSubjectsLookup(){return $this;}
  public function getPageSubjects($id){return \Harness\State::$page;}
  public function getSchemaResolverFor($auth){return $this;}
  public function getSchema($ref){if(\Harness\State::$schemaError)throw new \RuntimeException('schema');return \Harness\State::$schema;}
  public function getSubjectIdParser(){return new \ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser('local');}
  public function newIdentityBoundaryResolver(){return new class {public function entity($id){return $id;}};}
  public function newSubjectHostingPageResolver($auth){return $this;}
  public function resolveReadableHostingPage($id){return \Harness\State::$targetAllowed ? new class { public function getId(){return new \ProfessionalWiki\SWS\Domain\Page\PageId(42);} public function getTitle(){return 'Zielseite';} } : null;}
 }
}
namespace SWSBaseBridge {
 class PageIdentityLookup {
  public function wikidata($title){return ['status'=>'resolved','id'=>'Q619','url'=>'https://www.wikidata.org/wiki/Q619'];}
  public function wikibase($title,$auth){return ['status'=>'missing'];}
 }
 class EntityGateway {
  public function __construct($context){}
  public function conceptUri($source,$id){return ($source==='wikidata'?'http://www.wikidata.org/entity/':'https://example.org/entity/').$id;}
  public function entities($source,$ids,$language,$claims){if(\Harness\State::$sourceError)throw new \RuntimeException('source');return ['Q619'=>['descriptions'=>[$language=>['language'=>'de','value'=>'Astronom']]]];}
 }
 class ExternalValuesLookup {
  public function __construct($context){}
  public function read($title,$p,$dependencies=null,$subject=null){\Harness\State::$seen[]=$subject->id->text;return ['fields'=>[['name'=>'Klasse','status'=>'resolved','source'=>'wikidata','values'=>[['kind'=>'entity','id'=>'Q5','label'=>'Mensch']]]]];}
 }
}
namespace Harness {
 class State {public static $page,$schema,$shortdesc=null;public static bool $pagePropError=false;public static bool $sourceError=false,$schemaError=false,$targetAllowed=false;public static array $seen=[];}
 class Context implements \MediaWiki\Context\IContextSource, \MediaWiki\Permissions\Authority {
  public bool $allow=true;
  public function getAuthority(){return $this;}
  public function authorizeRead($right,$title){return $this->allow;}
 }
}
namespace {
 require __DIR__.'/bootstrap.php';
 use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,SchemaReference,PropertyDefinitions,PropertyCore,PropertyName};
 use ProfessionalWiki\SWS\Domain\Schema\Property\TextProperty;
 use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList,SubjectMap};
 use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
 $checks=0;
 function check($condition,$why){global $checks;if(!$condition)throw new RuntimeException($why);$checks++;}
 $core=new PropertyCore('',false,null);
 \Harness\State::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Klasse'=>TextProperty::fromPartialJson($core,[])]),false);
 $main=new Subject(new SubjectId('sF1FCQPNcwQdw4B'),null,SchemaReference::local(new SchemaName('Person')),new StatementList([]));
 $other=new Subject(new SubjectId('sF16iHXBMTQdHDY'),null,SchemaReference::local(new SchemaName('Person')),new StatementList([]));
 \Harness\State::$page=new PageSubjects($main,new SubjectMap($other));
 \ProfessionalWiki\SWS\SWSExtension::$instance=new \ProfessionalWiki\SWS\SWSExtension();
 $context=new \Harness\Context();$lookup=new \SWSBaseBridge\DataViewLookup($context);$title=new \MediaWiki\Title\Title();
 $v=$lookup->read($title,'de');
 check(count($v['subjects'])===2,'all page subjects');check($v['subjects'][0]['isMainSubject']===true,'main');check($v['subjects'][1]['isMainSubject']===false,'other');
 check(\Harness\State::$seen===[$main->id->text,$other->id->text],'source request bound to each subject');
 check($v['subjects'][0]['fields'][0]['effectiveValues'][0]['label']==='Mensch','derived value');check($v['identities']['wikidata']['description']['value']==='Astronom','description');check($v['complete']===true,'complete');
 $v=$lookup->read($title,'de',$other->id->text);check(count($v['subjects'])===1 && $v['subjects'][0]['id']===$other->id->text,'selection');
 try{$lookup->read($title,'de','not-on-page');throw new Exception('accepted');}catch(RuntimeException){check(true,'foreign subject rejected');}
 $context->allow=false;try{$lookup->read($title,'de');throw new Exception('accepted');}catch(RuntimeException){check(true,'read denied');}$context->allow=true;
 try{$lookup->read($title,'../../');throw new Exception('accepted');}catch(RuntimeException){check(true,'bad language rejected');}
 \Harness\State::$sourceError=true;$v=$lookup->read($title,'de');check($v['identities']['wikidata']['id']==='Q619','identity retained on source failure');check($v['complete']===false,'description failure explicit');\Harness\State::$sourceError=false;
 \Harness\State::$schemaError=true;$v=$lookup->read($title,'de');check($v['subjects'][0]['schemaStatus']==='unavailable' && !$v['complete'],'missing schema');
 \Harness\State::$schemaError=false;
 $r=new \ProfessionalWiki\SWS\Domain\Relation\Relation(new \ProfessionalWiki\SWS\Domain\Relation\RelationId('rF1FCQPNcwQdw4B'),$other->id);
 $main->setStatements(new StatementList([new \ProfessionalWiki\SWS\Domain\Statement(new PropertyName('Bezug'),'relation',new \ProfessionalWiki\SWS\Domain\Value\RelationValue($r)),new \ProfessionalWiki\SWS\Domain\Statement(new PropertyName('Studium'),'page',new \ProfessionalWiki\SWS\Domain\Value\StringValue('Universität Wien'))]));
 $v=$lookup->read($title,'de');
 check($v['subjects'][0]['fields'][1]['ownValues'][0]['target']===['id'=>$other->id->text,'status'=>'unavailable'],'unreadable target metadata withheld');
 check(str_contains($v['subjects'][0]['fields'][2]['ownValues'][0]['url'],'Universit'),'local page URL');
 \Harness\State::$targetAllowed=true;$v=$lookup->read($title,'de');
 check($v['subjects'][0]['fields'][1]['ownValues'][0]['target']['label']==='Person','readable target label');
 check($v['subjects'][0]['fields'][1]['ownValues'][0]['target']['pageTitle']==='Zielseite','readable target page');
 echo "OK: $checks lookup integration contracts (stand-in host/source services)\n";
}
