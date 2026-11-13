<?php
declare( strict_types = 1 );
namespace MediaWiki\Rest {
 class Response { public int $status=200; public array $headers=[]; public mixed $data=null; public string $body=''; public function setBody($body):void{$this->body=(string)$body;} public function setHeader($k,$v):void{$this->headers[$k]=$v;} public function addHeader($k,$v):void{$this->headers[$k]=$v;} }
 class StringStream { public function __construct(private string $text){} public function __toString():string{return $this->text;} }
 class SimpleHandler {
  public const PARAM_SOURCE='source';
  public function getValidatedParams():array{return ['search'=>'E1','limit'=>10,'schema'=>null,'language'=>'en'];}
  public function getAuthority():object{return new \stdClass();}
  public function getResponseFactory():object{return new class{
   public function create():Response{return new Response();}
   public function createJson($data):Response{$r=new Response();$r->data=$data;$r->body=json_encode($data);return $r;}
   public function createHttpError($status,$data):Response{$r=$this->createJson($data);$r->status=$status;return $r;}
   public function createSeeOther($url):Response{$r=new Response();$r->status=303;$r->headers['Location']=$url;return $r;}
  };}
  public function getRequest():object{return new class{public function getHeaderLine($name):string{return \D4Rest\State::$accept;}};}
  public function getRouter():object{return new class{public function getRouteUrl($path,$params,$query=[]):string{return 'https://wiki.example/rest.php'.strtr($path,array_combine(array_map(fn($key)=>'{'.$key.'}',array_keys($params)),array_values($params))).'?'.http_build_query($query);}};}
  public function getRouteUrl($params=[]):string{return 'https://wiki.example/rest.php/sws/v0/schema-id/'.$params['schemaName'].'/json-schema';}
 }
}
namespace MediaWiki\Title { class Title {public static function makeTitleSafe($ns,$name):object{return new \D4Rest\Title('Schema:'.$name);}} }
namespace MediaWiki\SpecialPage { class SpecialPage { public static function getTitleFor($name,$id):object{return new \D4Rest\Title('Special:Entity/'.$id);} } }
namespace MediaWiki { class MediaWikiServices { public static function getInstance():self{return new self();} public function getTitleFactory():object{return new class{public function newFromID($id):object{return new \D4Rest\Title('Article');}};} } }
namespace D4Rest { class State {public static bool $read=true,$subjectFirst=false,$present=true;public static string $accept='';} class Title {public function __construct(private string $text){}public function getCanonicalURL():string{return 'https://wiki.example/wiki/'.$this->text;}} }
namespace ProfessionalWiki\SWS {
 class SWSExtension {
  public const NS_SCHEMA=7474;
  public function getSchemaReferenceParser():mixed{return $GLOBALS['schemaParser'];}
  public function getSchemaResolverFor($authority):mixed{return new class {public function getSchema($reference):mixed{return \D4Rest\State::$read?$GLOBALS['resolver']->getSchema($reference):null;}};}
  public function getRdfNamespaces():mixed{return $GLOBALS['ns'];}
  public function getRdfSerializer():mixed{return new \ProfessionalWiki\SWS\Infrastructure\Rdf\HardfRdfSerializer($GLOBALS['ns']->prefixMap(),$GLOBALS['logger']);}

  public static function getInstance():self{return new self();}
  public function getSubjectIdParser(bool $resolve=false):mixed{return $resolve?$GLOBALS['canonical']:$GLOBALS['raw'];}
  public function isSubjectFirst():bool{return \D4Rest\State::$subjectFirst;}
  public function newSubjectHostingPageResolver($authority):mixed{
   $lookup=new \ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup(\D4Rest\State::$present?[[new \ProfessionalWiki\SWS\Domain\Subject\SubjectId('E1'),new \ProfessionalWiki\SWS\Domain\Page\PageIdentifiers(new \ProfessionalWiki\SWS\Domain\Page\PageId(12),'Article',0)]]:[]);
   return new \ProfessionalWiki\SWS\Application\SubjectHostingPageResolver($lookup,new \ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer(\D4Rest\State::$read));
  }
  public function getSubjectLabelLookup():mixed{return new class{public function getSubjectLabelsMatching($search,$limit,$schemas):array{return \D4Rest\State::$read?[new \ProfessionalWiki\SWS\Application\SubjectLabelLookupResult('E1','Gödel','Article')]:[];}};}
  public function newIdentityPresentationLookup($authority):mixed{return new class{
   public function getSubjects($ids):mixed{return \D4Rest\State::$read?new \ProfessionalWiki\SWS\Domain\Subject\SubjectMap($GLOBALS['subject']):new \ProfessionalWiki\SWS\Domain\Subject\SubjectMap();}
  };}
  public function getSchemaLookup():mixed{return new class implements \ProfessionalWiki\SWS\Application\SchemaLookup {public function getSchema(\ProfessionalWiki\SWS\Domain\Schema\SchemaName $name):?\ProfessionalWiki\SWS\Domain\Schema\Schema{return \D4Rest\State::$read?$GLOBALS['source']->getSchema($name):null;}};}
  public function getSchemaPresentationSerializer():mixed{return new \ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer();}
  public function newJsonSchemaSerializer(string $url):mixed{return new \ProfessionalWiki\SWS\Presentation\JsonSchemaSerializer($url);}
  public function newGetSchemaQuery($presenter):mixed{return new \ProfessionalWiki\SWS\Application\Queries\GetSchema\GetSchemaQuery($presenter,$this->getSchemaLookup(),$this->getSchemaPresentationSerializer(),fn($name)=>$GLOBALS['boundary']->schema(\ProfessionalWiki\SWS\Domain\Schema\SchemaReference::local(new \ProfessionalWiki\SWS\Domain\Schema\SchemaName($name)))->name);}
 }
}
namespace {
 require __DIR__.'/identity-projections-contract.php';
 $restChecks=0;
 function d4rest(bool $ok,string $why):void{global$restChecks;if(!$ok)throw new \RuntimeException($why);$restChecks++;}
 $concept=new \ProfessionalWiki\SWS\EntryPoints\REST\ResolveSubjectIriApi();
 $oldReply=$concept->run($old);$newReply=$concept->run('E1');
 d4rest($oldReply->status===303&&$oldReply->headers===$newReply->headers,'Old concept URI and new E-ID URI target the same authorized page');
 \D4Rest\State::$subjectFirst=true;
 d4rest(str_ends_with($concept->run($old)->headers['Location'],'Special:Entity/E1'),'Subject-first old link targets canonical entity view');
 \D4Rest\State::$accept='text/turtle';$rdfReply=$concept->run($old);
 d4rest($rdfReply->status===303&&str_contains($rdfReply->headers['Location'],'/subject/E1/rdf?format=turtle'),'RDF negotiation uses canonical E-ID');
 d4rest($rdfReply->headers['Vary']==='Accept','Content-negotiated redirects retain Vary header');
 \D4Rest\State::$read=false;$denied=$concept->run($old);\D4Rest\State::$read=true;\D4Rest\State::$present=false;$absent=$concept->run($old);
 d4rest($denied->status===404&&$absent->status===404&&$denied->body===$absent->body,'Alias resolution cannot expose a restricted hosting page');
 \D4Rest\State::$present=true;
 d4rest($concept->run('E0')->status===400,'Malformed new entity ID stays a clean client error');
 $idApi=new \ProfessionalWiki\SWS\EntryPoints\REST\GetSchemaByIdApi();
 d4rest($idApi->run('S1')->data['schema']['id']==='S1','Explicit schema-ID API ignores legacy name alias S1 to S2');
 $nameApi=new \ProfessionalWiki\SWS\EntryPoints\REST\GetSchemaApi();$legacy=json_decode($nameApi->run('S1')->body,true);
 d4rest($legacy['schema']['id']==='S2','Legacy schema-name API preserves the assigned name mapping');
 d4rest($idApi->run('S01')->status===400,'Malformed S-ID rejected by ID endpoint');
 $jsonApi=new \ProfessionalWiki\SWS\EntryPoints\REST\GetJsonSchemaByIdApi();$json=json_decode($jsonApi->run('S1')->body,true);
 d4rest($json['properties']['schema']['const']===['id'=>'S1'],'JSON Schema ID endpoint describes the explicit S-ID');
 \D4Rest\State::$read=false;
 d4rest($idApi->run('S1')->status===404&&$jsonApi->run('S1')->status===404,'Schema endpoints retain the read-authorized lookup gate');
 \D4Rest\State::$read=true;
 $labels=new \ProfessionalWiki\SWS\EntryPoints\REST\GetSubjectLabelsApi();$lookupReply=$labels->run();
 d4rest($lookupReply->status===200&&json_decode($lookupReply->body,true)[0]['description']==='Mathematician','Entity label endpoint emits own requested-language description');
 \D4Rest\State::$read=false;
 d4rest(json_decode($labels->run()->body,true)===[],'Label endpoint emits no restricted entity metadata');
 \D4Rest\State::$read=true;\D4Rest\State::$accept='';
 $schemaIri=new \ProfessionalWiki\SWS\EntryPoints\REST\ResolveSchemaIriApi(true);
 $legacyIri=new \ProfessionalWiki\SWS\EntryPoints\REST\ResolveSchemaIriApi(false);
 d4rest(str_ends_with($schemaIri->run('S1')->headers['Location'],'Schema:S1'),'New schema IRI redirects to ID-backed schema page');
 d4rest(str_ends_with($legacyIri->run('S1')->headers['Location'],'Schema:S2'),'Legacy named schema IRI respects its name alias');
 \D4Rest\State::$accept='application/json';
 d4rest(str_contains($schemaIri->run('S1')->headers['Location'],'/schema-id/S1'),'Schema IRI JSON redirects use explicit ID endpoint');
 \D4Rest\State::$accept='text/turtle';
 d4rest(str_contains($schemaIri->run('S1')->headers['Location'],'/schema-id/S1/rdf?format=turtle'),'Schema IRI RDF negotiation uses explicit ID endpoint');
 $schemaRdf=new \ProfessionalWiki\SWS\EntryPoints\REST\ExportSchemaRdfApi(true);
 $rdf=$schemaRdf->run('S1');
 d4rest($rdf->status===200&&str_contains($rdf->body,'Person')&&str_contains($rdf->body,'@de'),'Schema RDF handler emits own language-tagged metadata');
 \D4Rest\State::$read=false;
 d4rest($schemaIri->run('S1')->status===404&&$schemaRdf->run('S1')->status===404,'Schema IRI and RDF handlers hide restricted schemas');
 d4rest($schemaIri->run('S01')->status===400,'Schema IRI rejects malformed typed ID');
 echo "$restChecks D4 REST legacy/canonical/schema-ID/negotiation/privacy checks passed (real handlers with controlled HTTP and MediaWiki routing boundaries).\n";
}
