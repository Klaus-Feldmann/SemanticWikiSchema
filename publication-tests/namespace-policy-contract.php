<?php
// Real eligibility, data-action and write-authorizer code against isolated MediaWiki stand-ins.
namespace { class FormlessAction {} }
namespace MediaWiki\Title {
    class Title {
        public function __construct(public int $ns,public string $text='Beispiel/Unterseite',public int $id=1) {}
        public function getNamespace() { return $this->ns; }
        public function canExist() { return $this->ns>=0; }
        public function getArticleID() { return $this->id; }
        public function getText() { return $this->text; }
    }
    class TitleFactory {
        public function __construct(public Title $title) {}
        public function newFromID($id) { return $this->title; }
        public function newFromText($text) { return $this->title; }
    }
}
namespace MediaWiki\Permissions { interface Authority {} }
namespace MediaWiki {
    class MediaWikiServices {
        public static array $additional=[];
        public static function getInstance() { return new self; }
        public function getNamespaceInfo() { return new class { public function isContent($ns) { return in_array($ns,[0,102],true); } }; }
        public function getMainConfig() { return new class { public function get($key) { if($key!=='SWSAdditionalSubjectNamespaces')throw new \RuntimeException($key);return MediaWikiServices::$additional; } }; }
    }
}
namespace ProfessionalWiki\SWS {
    class SWSExtension {
        public const NS_SCHEMA=7474,NS_LAYOUT=7476,NS_MAPPING=7478;
        public static function getInstance() { return new self; }
        public function isConfigPage($title) { return $title->getNamespace()===8 && $title->getText()==='SWS'; }
    }
}
namespace {
    require dirname(__DIR__).'/tests/standalone/autoload.php';
    use ProfessionalWiki\SWS\Infrastructure\{SubjectNamespacePolicy,AuthorityBasedSubjectAuthorizer};
    use ProfessionalWiki\SWS\EntryPoints\Actions\SubjectsAction;
    use MediaWiki\Title\{Title,TitleFactory};
    use MediaWiki\MediaWikiServices as Services;
    $checks=0;function check($yes,$why){global $checks;$checks++;if(!$yes)throw new \RuntimeException($why);}
    foreach([0,102]as $ns)check(SubjectNamespacePolicy::allowsTitle(new Title($ns)),'Content namespaces stay available');
    foreach([8,10,828]as $ns)check(!SubjectNamespacePolicy::allowsTitle(new Title($ns)),'Additional namespace not enabled by default');
    Services::$additional=[8,10,828,7474,7476,7478,-1,'2'];
    foreach([8,10,828]as $ns){
        foreach(['Seite','Seite/Unterseite','Seite/Unterseite/JSON']as $name){
            $title=new Title($ns,$name);
            check(SubjectNamespacePolicy::allowsTitle($title),'Additional namespace/subpage admitted');
            check(SubjectsAction::isEligibleTitle($title),'Data action uses same policy');
        }
    }
    foreach([-2,-1,7474,7476,7478,2]as $ns)check(!SubjectNamespacePolicy::allowsTitle(new Title($ns)),'Virtual/management/unlisted namespaces rejected');
    check(!SubjectNamespacePolicy::allowsTitle(new Title(8,'SWS')),'SWS configuration page keeps dedicated UI');
    check(!SubjectsAction::isEligibleTitle(new Title(10,'Noch nicht angelegt',0)),'Data action requires existing page');
    $authority=new class implements \MediaWiki\Permissions\Authority {
        public bool $allowed=true;public int $writes=0;
        public function definitelyCan($right,$title){return $this->allowed;}
        public function authorizeWrite($right,$title){$this->writes++;return $this->allowed;}
    };
    $title=new Title(828);$factory=new TitleFactory($title);$id=new \ProfessionalWiki\SWS\Domain\Page\PageId(1);
    $authorizer=new AuthorityBasedSubjectAuthorizer($authority,$factory,SubjectNamespacePolicy::allowsTitle(...));
    check($authorizer->canEditSubject($id),'Permission hints allow eligible readable edit');
    check($authorizer->authorize($id) && $authority->writes===1,'Write still invokes real authorization boundary');
    $authority->allowed=false;
    check(!$authorizer->authorize($id),'Page protection remains binding');
    $authority->allowed=true;$factory->title=new Title(2);$before=$authority->writes;
    check(!$authorizer->authorize($id) && $authority->writes===$before,'Direct API write cannot bypass namespace policy');
    check(!$authorizer->authorizeCreatePage('User:Test'),'Page creation uses same namespace policy');
    echo "$checks namespace, subpage, data-action and authorization checks passed (isolated MediaWiki stand-ins).\n";
}
