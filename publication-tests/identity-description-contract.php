<?php
// Real API handler and read service; isolated MediaWiki adapters from the read-view test.
require __DIR__.'/lookup-contract.php';
class ApiBase {
 public const PARAM_TYPE='type',PARAM_MIN='min',PARAM_REQUIRED='required',PARAM_DFLT='default';
 public array $output=[];
 public function __construct(public array $params,public $context){}
 public function getMain(){return $this;}
 public function setCacheMode($mode){}
 public function extractRequestParams(){return $this->params;}
 public function getAuthority(){return $this->context->getAuthority();}
 public function getContext(){return $this->context;}
 public function getResult(){return $this;}
 public function getModuleName(){return 'swsbaseidentity';}
 public function addValue($parent,$name,$value){$this->output[$name]=$value;return true;}
 public function dieWithError($message,$code){throw new RuntimeException($code);}
 public function getLanguage(){return new class{public function getCode(){return 'de';}};}
}
$context=new \Harness\Context();
$params=['pageid'=>42,'descriptions'=>true,'language'=>'de'];
$api=new \SWSBaseBridge\ApiIdentity($params,$context);$api->execute();$value=$api->output['swsbaseidentity'];
check($value['descriptions'][0]['text']==='Astronom','real API returns shared description');
check($value['descriptions'][0]['sources'][0]['id']==='Q619','description source identity matches');
check($value['identities'][$value['mainSubjectId']]['wikidata']['id']==='Q619','old identity envelope retained');
check($api->isWriteMode()===false,'read-only handler');
$api=new \SWSBaseBridge\ApiIdentity($params+[], $context);$api->params['descriptions']=false;$api->execute();check(!isset($api->output['swsbaseidentity']['descriptions']),'old clients opt out');
\Harness\State::$sourceError=true;$api=new \SWSBaseBridge\ApiIdentity($params,$context);$api->execute();$value=$api->output['swsbaseidentity'];
check($value['descriptions']===[] && $value['identities'][$value['mainSubjectId']]['wikidata']['id']==='Q619','description failure keeps Q links');
\Harness\State::$sourceError=false;$context->allow=false;
try{(new \SWSBaseBridge\ApiIdentity($params,$context))->execute();throw new Exception('accepted');}catch(RuntimeException){check(true,'read denied');}
$context->allow=true;$params['language']='../../';
try{(new \SWSBaseBridge\ApiIdentity($params,$context))->execute();throw new Exception('accepted');}catch(RuntimeException){check(true,'language rejected');}

$params['language']='de';\Harness\State::$shortdesc='Eigene Seitenbeschreibung';
$api=new \SWSBaseBridge\ApiIdentity($params,$context);$api->execute();$value=$api->output['swsbaseidentity'];
check($value['displayDescription']['text']==='Eigene Seitenbeschreibung','SHORTDESC overrides source for display');
check($value['descriptions'][0]['text']==='Astronom','full source descriptions retained');
$view=(new \SWSBaseBridge\DataViewLookup($context))->read(new \MediaWiki\Title\Title(),'de');
check($view['displayDescription']['text']==='Eigene Seitenbeschreibung' && $view['pageDescription']['origin']==='shortdesc','MCP read view uses same selection');
\Harness\State::$shortdesc=null;\Harness\State::$pagePropError=true;
$view=(new \SWSBaseBridge\DataViewLookup($context))->read(new \MediaWiki\Title\Title(),'de');
check($view['pageDescription']['status']==='unavailable' && !$view['complete'],'page description error explicit');
\Harness\State::$pagePropError=false;
echo "OK: 12 API identity/description contracts (stand-in host services)\n";
