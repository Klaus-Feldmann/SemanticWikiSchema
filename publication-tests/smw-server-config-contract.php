<?php
// Real reader and validators with controlled MediaWiki messages/config; no Lua or network.
namespace MediaWiki\Parser { class Parser {} class ParserOutput {} }
namespace MediaWiki\User { class User {} }
namespace MediaWiki\Title { class Title {
    public function __construct(public int $namespace, public string $name) {}
    public static function makeTitleSafe(int $namespace,string $key):self { return new self($namespace,$key); }
} }
namespace MediaWiki {
    final class MediaWikiServices {
        public static array $settings = [];
        public static function getInstance(): self { return new self; }
        public function getMainConfig(): object { return new class {
            public function get(string $key): mixed {
                if ($key !== 'WikiSchemaSmwMapping') { throw new \RuntimeException('Unexpected setting'); }
                return MediaWikiServices::$settings;
            }
        }; }
        public function __call(string $name,array $args):never { throw new \RuntimeException('Unexpected wiki/Lua service '.$name); }
    }
}
namespace {
    require __DIR__.'/bootstrap.php'; require_once __DIR__.'/message-fixture.php';
    use AEWI\SWS\EntryPoints\Semantic\WikiAnnotationConfig;
    use AEWI\SWS\Application\PageDependencyRecorder;
    use AEWI\SWS\Domain\Schema\SchemaReference;
    $recorder = new class implements PageDependencyRecorder {
        public array $keys=[];
        public function recordDependencyOn(\MediaWiki\Title\Title $page):void {
            if ($page->namespace !== NS_MEDIAWIKI) { throw new \RuntimeException('Wrong dependency namespace'); }
            $this->keys[]=$page->name;
        }
        public function recordDependencyOnSchema(SchemaReference $schema):void {}
    };
    $reader=new WikiAnnotationConfig(); $args=[null,new \MediaWiki\Parser\ParserOutput(),new \MediaWiki\User\User()];
    $GLOBALS['swsTestContentLanguage']='de'; $GLOBALS['swsTestUiLanguage']='en';
    $config=$reader->read(...$args);
    if ($config['urlEntries']['subject']!=='WikiSchema-Entität'||$config['descriptionProperty']!=='Kurzbeschreibung'||count($config['messageDependencies'])!==7) { throw new \RuntimeException('Content-language defaults/dependencies failed'); }
    $GLOBALS['swsTestMessages']['de']['sws-smw-property-entity']='Einheitenkennung';
    $GLOBALS['swsTestMessages']['de']['sws-smw-property-description']='Zusammenfassung';
    $config=$reader->read(...$args);
    if ($config['urlEntries']['subject']!=='Einheitenkennung'||$config['descriptionProperty']!=='Zusammenfassung') { throw new \RuntimeException('Wiki overrides ignored'); }
    \MediaWiki\MediaWikiServices::$settings=['urlEntries'=>['subject'=>'Eigene Kennung'],'descriptionProperty'=>'Eigene Beschreibung'];
    // An invalid unused message must not override/refuse an explicitly configured valid name.
    $GLOBALS['swsTestMessages']['de']['sws-smw-property-entity']='Bad=Name';
    $GLOBALS['swsTestMessages']['de']['sws-smw-property-description']='';
    $recorder->keys=[]; $config=$reader->read(...$args);
    if ($config['urlEntries']['subject']!=='Eigene Kennung'||$config['descriptionProperty']!=='Eigene Beschreibung'||count($config['messageDependencies'])!==5) { throw new \RuntimeException('Explicit mapping priority/dependencies failed'); }
    \MediaWiki\MediaWikiServices::$settings=[];
    try { $reader->read(...$args); throw new \RuntimeException('Invalid message accepted'); } catch (\InvalidArgumentException) {}
    $GLOBALS['swsTestMessages']=[];
    $GLOBALS['swsTestContentLanguage']='en'; $GLOBALS['swsTestUiLanguage']='de';
    if ($reader->read(...$args)['urlEntries']['subject']!=='WikiSchema-Entity') { throw new \RuntimeException('UI language leaked into persisted names'); }
    echo "Content-language SMW names, seven message dependencies, explicit priority and invalid-name refusal passed.\n";
}
