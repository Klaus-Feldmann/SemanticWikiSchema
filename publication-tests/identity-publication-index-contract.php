<?php
declare(strict_types=1);
namespace MediaWiki\Content {
 class JsonContent {public function __construct(protected string $text,protected string $model=''){}public function getText():string{return $this->text;}}
}
namespace MediaWiki\Permissions {interface Authority {}}
namespace MediaWiki\Revision {
 class RevisionRecord {
  public function __construct(private int $id,private int $pageId,private string $name,private array $slots){}
  public function getId():int{return $this->id;}public function getPageId():int{return $this->pageId;}
  public function getPage():object{return new class($this->name){public function __construct(public string $name){}public function getDBkey():string{return $this->name;}};}
  public function getTimestamp():string{return '20261112120000';}
  public function hasSlot($role):bool{return isset($this->slots[$role]);}
  public function getContent($role):mixed{return $this->slots[$role]??null;}
  public function getSlots():object{return new class($this->slots){public function __construct(private array $slots){}public function getContent($role):mixed{return $this->slots[$role]??null;}};}
 }
}
namespace MediaWiki\Title {class TitleFormatter {public function getPrefixedText($page):string{return $page->name;}}}
namespace {
require __DIR__.'/identity-term-index-contract.php';
use ProfessionalWiki\SWS\Domain\Page\{PageSubjects,PageProperties,PageId};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,SubjectLabel,SubjectMap,StatementList};
use ProfessionalWiki\SWS\Domain\Schema\{SchemaName,SchemaReference};
use ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity;
use ProfessionalWiki\SWS\EntryPoints\Content\{SubjectContent,SchemaContent};
use MediaWiki\Revision\RevisionRecord;
class PublishedFixtureContent extends SubjectContent {
 public function __construct(private PageSubjects $subjects){parent::__construct('{}');}
 public function getPageSubjects():PageSubjects{return $this->subjects;}
 public function getSubjectIds():array{return $this->subjects->getAllSubjects()->getIdsAsTextArray();}
}
$policy=new class implements \ProfessionalWiki\SWS\Application\RevisionPolicy {
 public bool $visible=true;public ?RevisionRecord $published=null;
 public function publishedRevision(RevisionRecord $revision):?RevisionRecord{return $this->visible?($this->published??$revision):null;}
 public function revisionIsReadableBy(RevisionRecord $revision,\MediaWiki\Permissions\Authority $user):bool{return true;}
};
$graph=new \ProfessionalWiki\SWS\Tests\TestDoubles\SpyGraphDatabasePlugin();
$host=new \ProfessionalWiki\SWS\Tests\TestDoubles\SpySubjectPageIndex();
$published=new \ProfessionalWiki\SWS\Tests\TestDoubles\SpyPublishedSubjectIndex();
$properties=new class implements \ProfessionalWiki\SWS\PagePropertiesSource{public function getPagePropertiesFor(RevisionRecord $revision):?PageProperties{return new PageProperties(['name'=>'Visible page']);}};
$handler=new \ProfessionalWiki\SWS\EntryPoints\OnRevisionCreatedHandler($graph,$host,$published,$properties,$policy,new \MediaWiki\Title\TitleFormatter(),new \Psr\Log\NullLogger(),$index);
$schema=new RevisionRecord(500,201,'S1',['main'=>new SchemaContent('{"formatVersion":2,"id":"S1","labels":{"de":"Person","en":"Human"}}')]);
$handler->onRevisionCreated($schema);
termCheck((int)$db->pdo->query("SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=201")->fetchColumn()===3,'Schema save publishes own labels and key into term index');
$subject=new Subject(new SubjectId('E1'),new SubjectLabel('Person'),SchemaReference::local(new SchemaName('S1')),new StatementList(),identity:new LocalizedIdentity('de',['de'=>'Person','en'=>'Human'],['de'=>'Beschreibung']));
$entity=new RevisionRecord(501,202,'Visible page',['main'=>new \MediaWiki\Content\JsonContent('Article'),'sws'=>new PublishedFixtureContent(new PageSubjects($subject,new SubjectMap()))]);
$handler->onRevisionCreated($entity);
termCheck((int)$db->pdo->query('SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=202')->fetchColumn()===4,'Actual publication handler indexes all own entity language terms');
$subject2=clone $subject;$subject2->setIdentity(new LocalizedIdentity('de',['de'=>'Neu','en'=>'New'],[]));
$draft=new RevisionRecord(502,202,'Visible page',['main'=>new \MediaWiki\Content\JsonContent('Article'),'sws'=>new PublishedFixtureContent(new PageSubjects($subject2,new SubjectMap()))]);
$policy->published=$entity;$handler->onRevisionCreated($draft);
termCheck((int)$db->pdo->query("SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=202 AND swst_key='new'")->fetchColumn()===0,'Unpublished draft labels never enter search index');
$policy->published=null;$handler->onRevisionCreated($draft);
termCheck((int)$db->pdo->query("SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=202 AND swst_key='new'")->fetchColumn()===1,'Published rename updates derived language index');
$policy->visible=false;$handler->onRevisionCreated($draft);
termCheck((int)$db->pdo->query('SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=202')->fetchColumn()===0,'Publication withdrawal removes term index together with published subjects');
$handler->onPageDelete(201);termCheck((int)$db->pdo->query('SELECT COUNT(*) FROM sws_search_term WHERE swst_page_id=201')->fetchColumn()===0,'Page deletion removes schema terms as well');
echo "6 D5 actual revision-publication/index/rename/draft/withdrawal/deletion checks passed (controlled revision host with real SQLite index).\n";
}
