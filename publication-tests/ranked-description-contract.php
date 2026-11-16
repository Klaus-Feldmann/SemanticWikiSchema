<?php
namespace MediaWiki\Parser { class ParserOutput {
 public array $props=[];public function getPageProperty($k){return $this->props[$k]??null;}public function setPageProperty($k,$v){$this->props[$k]=$v;}
} }
namespace MediaWiki\Title { class Title {} }
namespace MediaWiki\User { class User {} }
namespace MediaWiki\Context { class RequestContext{static function getMain(){return new self;}}class DerivativeContext{function __construct($x){}function setUser($x){}} }
namespace MediaWiki { class MediaWikiServices {
 static function getInstance(){return new self;}function getContentLanguage(){return new class{function getCode(){return 'de';}};}
 function getMainConfig(){return new class {function get($k){return \DescriptionHarness::$mode;}};}
} }
namespace SWSBaseBridge {
 class StoredSourceLookup{function __construct($c){}function identities($t,$l){\DescriptionHarness::$calls[]='store';return \DescriptionHarness::$sources;}}
 class DataViewLookup{function __construct($c){}function readIdentities($t,$l){\DescriptionHarness::$calls[]='cache';return \DescriptionHarness::$sources;}}
}
namespace {
 require __DIR__.'/bootstrap.php';
 use ProfessionalWiki\SWS\EntryPoints\Processing\EffectivePageDescription as Ranked;
 class DescriptionHarness {static $mode='store';static $sources=[];static $calls=[];}
 $n=0;function verify($ok,$why){global $n;$n++;if(!$ok)throw new RuntimeException($why);}
 $t=new \MediaWiki\Title\Title;$u=new \MediaWiki\User\User;$ranked=new Ranked;
 $item=static fn($s)=>['status'=>'resolved','description'=>['value'=>$s,'language'=>'de']];
 DescriptionHarness::$sources=['wikidata'=>$item('Extern'),'wikibase-local'=>$item('Lokal')];
 foreach(['store','cache'] as $mode){
  DescriptionHarness::$mode=$mode;DescriptionHarness::$calls=[];
  $out=new \MediaWiki\Parser\ParserOutput;$out->props['shortdesc']='Eigene Beschreibung';$ranked->apply($t,$out,$u);
  verify($out->props['sws-description']==='Eigene Beschreibung'&&$out->props['sws-description-source']==='shortdesc','Parsed SHORTDESC wins in '.$mode);
  verify(DescriptionHarness::$calls===[],'SHORTDESC requires no source lookup');
  $out=new \MediaWiki\Parser\ParserOutput;$ranked->apply($t,$out,$u);
  verify($out->props['sws-description']==='Lokal'&&DescriptionHarness::$calls===[$mode],'Correct ranked projection and selected source mode');
 }
 DescriptionHarness::$sources=['wikidata'=>$item('Extern')];$out=new \MediaWiki\Parser\ParserOutput;$ranked->apply($t,$out,$u);
 verify($out->props['sws-description']==='Extern','Missing local falls back to Wikidata');
 DescriptionHarness::$sources=[];$out=new \MediaWiki\Parser\ParserOutput;$ranked->apply($t,$out,$u);
 verify(!isset($out->props['sws-description']),'No placeholder after sources removed');
 echo "$n ranked-description processing/store/cache/SHORTDESC checks passed.\n";
}
