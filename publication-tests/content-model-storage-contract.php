<?php
// Real slot saver against an isolated PageUpdater stand-in; no live database access.
namespace MediaWiki\Content { class Content { public function __construct(public string $model,public string $text){} } }
namespace MediaWiki\Permissions { interface Authority {} }
namespace MediaWiki\CommentStore { class CommentStoreComment { public static function newUnsavedComment($text){return new self;} } }
namespace MediaWiki\Page {
    interface PageIdentity {}
    class WikiPageFactory {
        public function __construct(public \WikiPage $page){}
        public function newFromId($id){return $this->page;}
        public function newFromTitle($title){return $this->page;}
    }
}
namespace MediaWiki\Storage {
    class PageUpdater {
        private array $pending=[];
        public function __construct(private \WikiPage $page){}
        public function setContent($role,$content){$this->pending[$role]=$content;}
        public function saveRevision(...$args){$this->page->slots=array_replace($this->page->slots,$this->pending);}
        public function wasSuccessful(){return true;}
        public function wasRevisionCreated(){return true;}
        public function getNewRevision(){return new class{public function getPageId(){return 42;}};}
    }
}
namespace {
    define('EDIT_NEW',1);
    class WikiPage {
        public function __construct(public array $slots){}
        public function newPageUpdater($performer){return new \MediaWiki\Storage\PageUpdater($this);}
    }
    require dirname(__DIR__).'/tests/standalone/autoload.php';
    use MediaWiki\Content\Content;
    use ProfessionalWiki\SWS\Persistence\MediaWiki\{PageContentSaver,PageContentSavingStatus};
    $checks=0;function check($yes,$why){global $checks;$checks++;if(!$yes)throw new \RuntimeException($why);}
    foreach([
        'wikitext'=>"Vorlagentext <noinclude>Dokumentation</noinclude>",
        'Scribunto'=>"return { test = function() return 'Unverändert' end }",
        'json'=>'{"Name":"Unverändert","Zahl":42}',
        'javascript'=>"window.example = 'Unverändert';",
        'css'=>".beispiel { color: red; }"
    ]as $model=>$text){
        $main=new Content($model,$text);$other=new Content('json','{"andererSlot":true}');
        $page=new WikiPage(['main'=>$main,'other'=>$other]);
        $saver=new PageContentSaver(new \MediaWiki\Page\WikiPageFactory($page),new class implements \MediaWiki\Permissions\Authority{});
        $comment=\MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment('isolated');
        foreach(['erste Werte','geänderte Werte','']as $value){
            $subject=new Content('SWSSubject',$value);
            $status=$saver->saveContent(new \ProfessionalWiki\SWS\Domain\Page\PageId(42),['sws'=>$subject],$comment);
            check($status->status===PageContentSavingStatus::REVISION_CREATED,'Successful slot save: '.$model);
            check($page->slots['main']===$main && $page->slots['main']->text===$text && $page->slots['main']->model===$model,'Main content/model unchanged: '.$model);
            check($page->slots['other']===$other && $page->slots['sws']===$subject,'Only requested subject slot replaced: '.$model);
        }
    }
    echo "$checks content-model slot-storage/preservation checks passed (isolated MediaWiki stand-ins).\n";
}
