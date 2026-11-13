<?php
declare(strict_types=1);
namespace MediaWiki\Content {
 class Content {}
 class JsonContent extends Content {public function __construct(private string $text){}public function getText():string{return $this->text;}}
 class WikitextContent extends Content {public function __construct(private bool $redirect){}public function isRedirect():bool{return $this->redirect;}}
}
namespace MediaWiki\Permissions {interface Authority {}}
namespace MediaWiki\Title {class Title {}}
namespace MediaWiki\Revision {class RevisionRecord {public function getId():int{return 123;}}class SlotRecord {public const MAIN='main';}}
namespace Wikimedia\ObjectCache {class WANObjectCache {public const TTL_DAY=86400;public function makeKey(...$args):string{return implode(':',$args);}public function getWithSetCallback($key,$ttl,$callback):mixed{return $callback();}}}
namespace ProfessionalWiki\SWS\Persistence\MediaWiki {
 require __DIR__.'/bootstrap.php';
 class RedirectFixtureFetcher extends PageContentFetcher {
  public int $reads=0;
  public function __construct(public ?\MediaWiki\Content\Content $content){}
  public function getPublishedRevision(\MediaWiki\Title\Title $title):?\MediaWiki\Revision\RevisionRecord{return new \MediaWiki\Revision\RevisionRecord();}
  public function getRevisionContent(\MediaWiki\Revision\RevisionRecord $revision,\MediaWiki\Permissions\Authority $authority,string $slotName):?\MediaWiki\Content\Content{$this->reads++;return $this->content;}
 }
}
namespace {
 $fetcher=new \ProfessionalWiki\SWS\Persistence\MediaWiki\RedirectFixtureFetcher(new \MediaWiki\Content\WikitextContent(true));
 $lookup=new \ProfessionalWiki\SWS\Persistence\MediaWiki\PublishedPageJsonLookup($fetcher,new class implements \MediaWiki\Permissions\Authority {},new \Wikimedia\ObjectCache\WANObjectCache());
 $page=new \MediaWiki\Title\Title();$checks=0;
 function redirectCheck(bool $ok,string $why):void{global $checks;if(!$ok)throw new \RuntimeException($why);$checks++;}
 redirectCheck($lookup->getJson($page)===null,'Retired schema redirect is not a schema definition');
 redirectCheck($fetcher->reads===1,'Redirect target is not followed');
 $fetcher->content=new \MediaWiki\Content\JsonContent('{"id":"S1"}');
 redirectCheck($lookup->getJson($page)==='{"id":"S1"}','Valid schema JSON preserved');
 $fetcher->content=null;redirectCheck($lookup->getJson($page)===null,'Unreadable content remains absent');
 $fetcher->content=new \MediaWiki\Content\WikitextContent(false);
 try{$lookup->getJson($page);throw new \RuntimeException('Non-redirect corruption was hidden');}catch(\LogicException){$checks++;}
 require dirname(__DIR__).'/i18n/_Aliases.php';
 redirectCheck($specialPageAliases['de']['Schemas']===['Schemata','Schemas'],'German name and former alias preserved');
 echo "$checks actual JSON-reader/alias checks passed (controlled MediaWiki content adapter).\n";
}
