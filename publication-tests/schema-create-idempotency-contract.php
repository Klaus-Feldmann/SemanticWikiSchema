<?php
declare(strict_types=1);
namespace MediaWiki\Content {class JsonContent {public function __construct(private string $text,private string $model=""){}public function getText():string{return $this->text;}}}
namespace MediaWiki\Revision {class RevisionRecord {public const DELETED_TEXT=2,FOR_THIS_USER=1;}}
namespace UtfNormal {class Validator {public static function cleanUp($text):string{return $text;}}}
namespace D6Api {class State {public static array $body=[],$pages=[];public static int $writes=0,$csrf=0;public static bool $token=true,$allow=true,$read=true,$revisionRead=true,$policyRead=true;}}
namespace MediaWiki\Rest {
 class Response {public mixed $data;public function __construct($data=null){$this->data=$data;}}
 class HttpException extends \RuntimeException {}
 class ResponseException extends \RuntimeException {}
 class LocalizedHttpException extends HttpException {}
}
namespace MediaWiki\Rest\Handler {
 class ActionModuleBasedHandler {
  public const PARAM_SOURCE='source',PARAM_DESCRIPTION='description';
  public function getValidatedBody():array{return \D6Api\State::$body;}
  public function getValidatedParams():array{return [];}
  public function getRequest():object{return new class{public function getBody():string{return json_encode(\D6Api\State::$body);}};}
  public function getAuthority():object{return new class{public function isAllowed($right):bool{return \D6Api\State::$allow;}};}
  public function getUser():object{return new class{public function getEditToken():string{return 'fixture-token';}};}
  public function getResponseFactory():object{return new class{public function createJson($data):\MediaWiki\Rest\Response{return new \MediaWiki\Rest\Response($data);}public function createHttpError($code,$data):\MediaWiki\Rest\Response{return new \MediaWiki\Rest\Response(['code'=>$code]+$data);}};}
  public function execute():\MediaWiki\Rest\Response {
   $params=$this->getActionModuleParameters();$name=substr($params['title'],7);\D6Api\State::$pages[$name]=$params['text'];\D6Api\State::$writes++;
   return $this->getResponseFactory()->createJson($this->mapActionModuleResult(['edit'=>['result'=>'Success']]));
  }
 }
}
namespace MediaWiki\Title {
 class Title {public function __construct(private string $name){}public static function makeTitleSafe($ns,$name):self{return new self($name);}public function exists():bool{return isset(\D6Api\State::$pages[$this->name]);}public function getText():string{return $this->name;}public function getPrefixedDBkey():string{return 'Schema:'.$this->name;}}
}
namespace MediaWiki {
 class MediaWikiServices {public static function getInstance():self{return new self();}public function getContentLanguage():object{return new class{public function getCode():string{return 'de';}};}public function getRevisionLookup():object{return new class{public function getRevisionByTitle($title):object{return new class($title){public function __construct(private $title){}public function userCan(...$args):bool{return \D6Api\State::$revisionRead;}public function getContent($slot,...$args):object{return new \ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent(\D6Api\State::$pages[$this->title->getText()]);}};}};}}
}
namespace ProfessionalWiki\SWS\Presentation {
 class CsrfValidator {public function verifyCsrfToken():bool{\D6Api\State::$csrf++;if(!\D6Api\State::$token)throw new \MediaWiki\Rest\HttpException('CSRF',403);return true;}}
}
namespace ProfessionalWiki\SWS {
 class SWSExtension {
  public const NS_SCHEMA=7474;
  public static function getInstance():self{return new self();}
  public function newIdentityRegistry():mixed{return $GLOBALS['registry'];}
  public function getRevisionPolicy():object{return new class{public function revisionIsReadableBy($revision,$authority):bool{return \D6Api\State::$policyRead;}};}
  public function getIdentityOwner($authority):string{return hash('sha256','actor');}
  public function newPageReadAuthorizer($authority):object{return new class{public function authorizeReadByPageTitle($title):bool{return \D6Api\State::$read;}};}
  public function getPersistenceSchemaDeserializer():mixed{return new \ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaPersistenceDeserializer(\ProfessionalWiki\SWS\Tests\Data\TestSources::newPropertyTypeRegistry());}
  public function getSchemaPresentationSerializer():mixed{return new \ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer();}
 }
}
namespace {
require __DIR__.'/identity-projections-contract.php';
$nCreate=0;function createCheck(bool $ok,string $why):void{global$nCreate;$nCreate++;if(!$ok)throw new RuntimeException($why);}
use D6Api\State as A;
use ProfessionalWiki\SWS\EntryPoints\REST\CreateSchemaApi;
use ProfessionalWiki\SWS\Presentation\CsrfValidator;
A::$body=['requestKey'=>'stable-import','schema'=>(object)['label'=>'Import','propertyDefinitions'=>(object)[]]];
$reply=(new CreateSchemaApi(new CsrfValidator()))->execute();$id=$reply->data['schema']['id'];
createCheck(A::$writes===1&&$id==='S3','Actual create handler writes a fresh owned ID schema');
$changed=json_decode(A::$pages[$id],true);$changed['labels']['de']='Independently edited';A::$pages[$id]=json_encode($changed);$original=A::$pages[$id];
$reply=(new CreateSchemaApi(new CsrfValidator()))->execute();
createCheck(A::$writes===1&&A::$pages[$id]===$original&&$reply->data['schema']['labels']->de==='Independently edited','Retry returns current resource instead of overwriting an edited/completed schema with its initial shell');
createCheck(A::$csrf===2,'Idempotent return still verifies CSRF on each request');
A::$token=false;try{(new CreateSchemaApi(new CsrfValidator()))->execute();throw new LogicException('No CSRF check');}catch(\MediaWiki\Rest\HttpException $e){createCheck($e->getCode()===403&&A::$writes===1,'Invalid CSRF never writes');}A::$token=true;
A::$allow=false;try{(new CreateSchemaApi(new CsrfValidator()))->execute();throw new LogicException('No right check');}catch(\MediaWiki\Rest\HttpException $e){createCheck($e->getCode()===403&&A::$writes===1,'Creation rights remain required for retry');}A::$allow=true;
A::$read=false;try{(new CreateSchemaApi(new CsrfValidator()))->execute();throw new LogicException('No read check');}catch(\MediaWiki\Rest\HttpException $e){createCheck($e->getCode()===404,'Restricted page is not returned by idempotent create');}A::$read=true;
foreach(['revisionRead','policyRead']as$restriction){A::${$restriction}=false;try{(new CreateSchemaApi(new CsrfValidator()))->execute();throw new LogicException('Hidden revision returned');}catch(\MediaWiki\Rest\HttpException $e){createCheck($e->getCode()===404&&A::$writes===1,'Retry respects revision visibility and registered policy');}A::${$restriction}=true;}
A::$pages['S4']='{"propertyDefinitions":{}}';A::$body['requestKey']='legacy-collision';
try{(new CreateSchemaApi(new CsrfValidator()))->execute();throw new LogicException('Collision ignored');}catch(\MediaWiki\Rest\HttpException $e){createCheck($e->getCode()===409&&A::$writes===1&&A::$pages['S4']==='{"propertyDefinitions":{}}','Legacy page cannot be replaced by a reserved ID');}
class SaveParamsProbe extends \ProfessionalWiki\SWS\EntryPoints\REST\SaveSchemaApi {public function params():array{return $this->getActionParameters();}}
A::$body['baseRevisionId']=77;$params=(new SaveParamsProbe(new CsrfValidator()))->params();
createCheck($params['baserevid']==='77','Schema completion forwards exact base revision to MediaWiki edit CAS');
echo "$nCreate D6 actual schema create/retry/CAS/permission/CSRF/collision checks passed (real registry; controlled MediaWiki action adapter).\n";
}
