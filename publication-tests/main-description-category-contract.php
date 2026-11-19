<?php
// Actual slot content handler and category adapter, with stand-ins for MediaWiki services.
namespace MediaWiki\Content {
 class Content {}
 class JsonContentHandler {}
}
namespace MediaWiki\Content\Renderer { class ContentParseParams {} }
namespace MediaWiki\Parser {
 class ParserOutput {
  public array $categories=[];
  public string $text='';
  public function setContentHolderText($text):void {$this->text=$text;}
  public function addCategory($name,$sort):void {$this->categories[$name]=$sort;}
 }
}
namespace MediaWiki\Title {
 class Title {
  private function __construct(private string $text){}
  public static function newFromText($text,$default=0):?self {
   if(trim($text)===''||preg_match('/[\[\]{}|<>]/',$text))return null;
   return new self(preg_match('/^(Category|Kategorie):/i',$text)?$text:($default===14?'Category:':'').$text);
  }
  public function getNamespace():int{return preg_match('/^(Category|Kategorie):/i',$this->text)?14:0;}
  public function isExternal():bool{return false;}
  public function getDBkey():string{return str_replace(' ','_',preg_replace('/^[^:]+:/','',$this->text));}
 }
}
namespace AEWI\SWS\EntryPoints\Content {
 class SubjectContent extends \MediaWiki\Content\Content {
  public function __construct(private \AEWI\SWS\Domain\Page\PageSubjects $subjects){}
  public function getPageSubjects(){return $this->subjects;}
 }
}
namespace AEWI\SWS\EntryPoints\Processing {
 class SubjectSlotMetadata {
  public static function addCategories($content,$params,$output):void {$output->addCategory('Existing_schema_category','Keep');}
 }
}
namespace {
 require __DIR__.'/bootstrap.php';
 function wfMessage($key){return new class($key){public function __construct(public string $key){}public function inContentLanguage(){return $this;}public function text(){if($this->key!=='sws-entity-description-missing-category')throw new \RuntimeException('Wrong message');return $GLOBALS['categoryMessage'];}};}
 class HandlerProbe extends \AEWI\SWS\EntryPoints\Content\SubjectContentHandler {
  public function render($content):\MediaWiki\Parser\ParserOutput {
   $output=new \MediaWiki\Parser\ParserOutput();
   $this->fillParserOutput($content,new \MediaWiki\Content\Renderer\ContentParseParams(),$output);return $output;
  }
 }
 $checks=0;
 function check($value,$message){global $checks;if(!$value)throw new \RuntimeException($message);$checks++;}
 $handler=new HandlerProbe();
 $main=\AEWI\SWS\Tests\Data\TestSubject::build();
 $page=new \AEWI\SWS\Domain\Page\PageSubjects($main,new \AEWI\SWS\Domain\Subject\SubjectMap());
 $content=new \AEWI\SWS\EntryPoints\Content\SubjectContent($page);
 $GLOBALS['categoryMessage']='SWS Entitätsbeschreibung fehlt';
 $out=$handler->render($content);
 check(isset($out->categories['SWS_Entitätsbeschreibung_fehlt']),'Current slot without a main description receives the category');
 check($out->categories['Existing_schema_category']==='Keep','Existing schema categories survive');
 $GLOBALS['categoryMessage']='Category:Eigene Wartung';
 check(isset($handler->render($content)->categories['Eigene_Wartung']),'Overridden MediaWiki message chooses a different category');
 $GLOBALS['categoryMessage']='-';
 check(count($handler->render($content)->categories)===1,'Disabled message suppresses only the missing-description hint');
 $GLOBALS['categoryMessage']='[[Bad]]';
 check(count($handler->render($content)->categories)===1,'Invalid category name does not create a category');
 $GLOBALS['categoryMessage']='SWS Entitätsbeschreibung fehlt';
 $main->setIdentity(new \AEWI\SWS\Domain\Identity\LocalizedIdentity('de',[],['de'=>'Innere Zitate kennzeichnen.']));
 check(count($handler->render($content)->categories)===1,'Adding a description removes the derived category on a fresh parse');
 $main->setIdentity(new \AEWI\SWS\Domain\Identity\LocalizedIdentity('de',[],['de'=>'']));
 check(isset($handler->render($content)->categories['SWS_Entitätsbeschreibung_fehlt']),'Clearing it restores the hint on a fresh parse');
 echo "OK $checks main-description category integration checks\n";
}
