<?php
// Isolated exercise of the actual hook and heading formatter; no real MediaWiki runtime.
namespace MediaWiki\Page { interface PageIdentity {} }
namespace MediaWiki\Title {
 class Title implements \MediaWiki\Page\PageIdentity {
  function __construct(public string $text){} static function newFromPageIdentity($page){return $page;}
  function getPrefixedText(){return $this->text;} function getNsText(){return 'Entity';} function getText(){return 'E3';}
 }
}
namespace MediaWiki\Content { class JsonContent { function __construct(private string $text){} function getText(){return $this->text;} } }
namespace MediaWiki\Message { class Message {static function listParam($messages,$type){return $messages;}} }
namespace Wikimedia\Message {enum ListType {case SEMICOLON;} class MessageValue {static function new($key){return new self;}function plaintextParams(...$params){return $this;}} }
namespace MediaWiki\Html { class Html {static function element($tag,$attrs,$text){return '<'.$tag.'>'.htmlspecialchars($text,ENT_QUOTES).'</'.$tag.'>';}} }
namespace MediaWiki\Parser {class Parser {static function formatPageTitle($ns,$sep,$title){return '<span>'.$ns.$sep.$title.'</span>';}}}
namespace MediaWiki\Output {class OutputPage {function __construct(public string $heading){}function getPageTitle(){return $this->heading;}function setPageTitle($heading){$this->heading=$heading;}}}
namespace ProfessionalWiki\SWS {class SWSExtension {static function getInstance(){return new self;} function isConfigPage($title){return $title->text==='MediaWiki:SWS';} function getConfigValidator(){return new \ProfessionalWiki\SWS\Application\WikiConfig\ConfigValidator(new \ProfessionalWiki\SWS\Application\WikiConfig\ConfigSchema);}}}
namespace {
 class StatusValue {public array $errors=[];function fatal(...$error){$this->errors[]=$error;}}
 require __DIR__.'/bootstrap.php';
 use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
 use ProfessionalWiki\SWS\Presentation\SubjectLabelHtml;
 $n=0;function check($pass,$message){global $n;$n++;if(!$pass)throw new \RuntimeException($message);}
 $manifest=json_decode(file_get_contents(__DIR__.'/../extension.json'),true);
 check(isset($manifest['Hooks']['JsonValidateSave'])&&!isset($manifest['Hooks']['EditFilter']),'Correct core hook registered');
 foreach([['MediaWiki:SWS','{"subjectFirst":true}',0],['MediaWiki:SWS','{"subjectFirst":"wrong"}',1],['MediaWiki:SWS','{"unknown":true}',1],['MediaWiki:SWS','null',1],['MediaWiki:Other','{"subjectFirst":"wrong"}',0]] as [$title,$json,$count]){
  $status=new StatusValue;SWSHooks::onJsonValidateSave(new \MediaWiki\Content\JsonContent($json),new \MediaWiki\Title\Title($title),$status);
  check(count($status->errors)===$count,'Config case '.$title.' '.$json);
  if($count)check($status->errors[0][0]==='sws-config-invalid','API-visible error key');
 }
 foreach(['Entity:E3: History','<span>Entity:E3</span>: History','Permission denied']as $heading){
  $out=new \MediaWiki\Output\OutputPage($heading);SubjectLabelHtml::nameInHeading($out,new \MediaWiki\Title\Title('Entity:E3'),'Platon <script>');
  check($out->heading===($heading==='Permission denied'?$heading:'<bdi>Platon &lt;script&gt;</bdi>: History'),'Escaping and localized action preserved');
 }
 echo "$n D1 hook/heading checks passed with MediaWiki stand-ins.\n";
}
