<?php
namespace { require __DIR__.'/lookup-contract.php'; }
namespace MediaWiki\Context {
 class RequestContext {public static function getMain(){return new \Harness\Context();}}
 class DerivativeContext implements IContextSource {
  private $authority;public function __construct($parent){$this->authority=$parent->getAuthority();}
  public function setAuthority($authority){$this->authority=$authority;}
  public function getAuthority(){return $this->authority;}
  public function getLanguage(){return new class {public function getCode(){return 'de';}};}
 }
}
namespace MediaWiki\Rest {
 interface ResponseInterface {}
 class Response implements ResponseInterface {
  public array $headers=[];public $body;public int $status=200;
  public function setHeader($name,$value){$this->headers[$name]=$value;}
  public function getHeaderLine($name){return $this->headers[$name]??'';}
  public function setBody($body){$this->body=$body;}
 }
 class StringStream {public function __construct(private $text){}public function __toString(){return $this->text;}}
 class SimpleHandler {
  public const PARAM_SOURCE='source';
  public function __construct(public array $params,public $authority){}
  public function getAuthority(){return $this->authority;}
  public function getValidatedParams(){return $this->params;}
  public function getResponseFactory(){return $this;}
  public function create(){return new Response();}
  public function createHttpError($status,$data){$r=new Response();$r->status=$status;$r->body=json_encode($data);return $r;}
  public function applyCacheControl(ResponseInterface $response):void {$response->setHeader('Cache-Control','max-age=0');}
 }
}
namespace Wikimedia\ParamValidator {class ParamValidator{public const PARAM_TYPE='type',PARAM_DEFAULT='default',PARAM_REQUIRED='required';}}
namespace MediaWiki\Output {class OutputPage {public array $config=[];public function addJsConfigVars($key,$value){$this->config[$key]=$value;}}}
namespace {
 $context=new \Harness\Context();$page=new \SWSBaseBridge\ExportPageDataViewApi(['format'=>'json','language'=>'de'],$context);
 $r=$page->run(42);check($r->status===200,'JSON page succeeds');$view=json_decode((string)$r->body,true,512,JSON_THROW_ON_ERROR);
 check(count($view['subjects'])===2,'all page subjects');check($view['complete']===true,'complete snapshot');
 check($r->headers['Content-Type']==='application/json; charset=utf-8','JSON mime');check(str_contains($r->headers['Content-Disposition'],'page-42-data-view.json'),'filename');
 $page->applyCacheControl($r);check($r->headers['Cache-Control']==='private,no-store','private no-store after core cache policy');check($page->needsWriteAccess()===false,'read only');
 foreach(['turtle'=>'text/turtle','trig'=>'application/trig']as$format=>$mime){
  $api=new \SWSBaseBridge\ExportPageDataViewApi(['format'=>$format,'language'=>'de'],$context);$r=$api->run(42);
  check($r->status===200 && str_starts_with($r->headers['Content-Type'],$mime),'RDF mime '.$format);
  check(count((new \pietercolpaert\hardf\TriGParser())->parse((string)$r->body))>0,'RDF syntax '.$format);
 }
 $subject=new \SWSBaseBridge\ExportSubjectDataViewApi(['format'=>'json','language'=>'de'],$context);
 $r=$subject->run('sF1FCQPNcwQdw4B');$view=json_decode((string)$r->body,true,512,JSON_THROW_ON_ERROR);check(count($view['subjects'])===1 && $view['subjects'][0]['id']==='sF1FCQPNcwQdw4B','single subject');
 check($subject->run('bad')->status===400,'malformed subject rejected');check($page->run(999)->status===404,'missing page hidden');
 $context->allow=false;$denied=$page->run(42);check($denied->status===404 && $denied->body===$page->run(999)->body,'denied and missing indistinguishable');$context->allow=true;
 \Harness\State::$sourceError=true;$r=$page->run(42);check($r->status===503 && !isset($r->headers['Content-Disposition']),'no incomplete download');\Harness\State::$sourceError=false;
 $bad=new \SWSBaseBridge\ExportPageDataViewApi(['format'=>'json','language'=>'../../'],$context);check($bad->run(42)->status===400,'bad language');
 $out=new \MediaWiki\Output\OutputPage();\SWSBaseBridge\Hooks::onBeforePageDisplay($out);check($out->config['wgSWSCompleteExport']===true,'frontend flag registered');
 $routes=json_decode(file_get_contents(__DIR__.'/../SchemaBaseBridge/extension.json'),true)['RestRoutes'];check(count($routes)===2 && $routes[0]['method']==='GET','read-only routes registered');
 echo "OK: 19 REST export contracts plus existing 18 read-view contracts (stand-in host services)\n";
}
