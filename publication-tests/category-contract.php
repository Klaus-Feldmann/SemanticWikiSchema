<?php
// Isolated domain/processing contract; MediaWiki title and parser output are stand-ins.
namespace MediaWiki\Title {
    class Title {
        public string $model='json';
        private function __construct(private string $text) {}
        public static function newFromPageReference($page){return $page;}
        public static function makeTitleSafe($ns,$text){return new self(($ns===14?'Kategorie':$ns).':'.$text);}
        public function getContentModel(){return $this->model;}
        public function isSamePageAs($other){return $this->text===$other->text;}
        public function getArticleID(){return 1;}
        public function getLatestRevID(){return 1;}
        public static function newFromText($text) {
            if (trim($text)==='' || preg_match('/[\[\]{}|<>]/',$text)) return null;
            return new self(str_replace('_',' ',trim($text)));
        }
        public function getNamespace() { return str_starts_with($this->text,'Kategorie:') ? 14 : 0; }
        public function isExternal() { return str_starts_with($this->text,'w:'); }
        public function canExist() { return true; }
        public function getPrefixedText() { return $this->text; }
        public function getDBkey() { return str_replace(' ','_',substr($this->text,strlen('Kategorie:'))); }
    }
}
namespace MediaWiki\User { class User {public function authorizeRead(...$args){return \MediaWiki\MediaWikiServices::$readAllowed;}} }
namespace MediaWiki\Parser {
    class ParserOutput {
        public array $categories=[], $usedOptions=[], $dependencies=[];public $expiry=null;
        public function recordOption($key){$this->usedOptions[]=$key;}
        public function updateCacheExpiry($seconds){$this->expiry=$seconds;}
        public function addTemplate($title,...$args){$this->dependencies[]=$title;}
        public function getCategoryMap(){return $this->categories;}
        public function mergeTrackingMetaDataFrom($other){$this->categories=array_merge($this->categories,$other->categories);}
        public function mergeHtmlMetaDataFrom($other){}
        public function getCategoryNames() { return array_keys($this->categories); }
        public function addCategory($name,$sort) { $this->categories[$name]=$sort; }
    }
}
namespace MediaWiki {
    class MediaWikiServices {
        public static bool $readAllowed=true;public static $schema;
        public static function getInstance() { return new self; }
        public function getMainConfig(){return new class{public function get($key){return [];}};}
        public function getUserFactory(){return new class{public function newFromUserIdentity($identity){return new \MediaWiki\User\User;}};}
        public function getNamespaceInfo() { return new class { public function getCanonicalNamespaces() { return [0=>'',14=>'Category']; } public function isContent($ns){return $ns===0;} }; }
    }
}
namespace MediaWiki\Content\Renderer {
    class ContentParseParams {
        public function __construct(private $page){}
        public function getRevId(){return 1;}
        public function getPage(){return $this->page;}
        public function getParserOptions(){return new class{public function getOption($key){return null;}public function getUserIdentity(){return new \stdClass;}};}
    }
}
namespace MediaWiki\Logger {
    class LoggerFactory {public static int $warnings=0;public static function getInstance($key){return new class{public function warning(...$args){LoggerFactory::$warnings++;}};}}
}
namespace ProfessionalWiki\SWS\EntryPoints\Content {
    class SubjectContent {public function __construct(private $subjects){} public function getPageSubjects(){return $this->subjects;}}
}
namespace ProfessionalWiki\SWS {
    class SWSExtension {
        public const NS_SCHEMA=7474,NS_LAYOUT=7476,NS_MAPPING=7478;
        public static function getInstance(){return new self;}
        public function isConfigPage($title){return false;}
        public function getSchemaResolverFor($authority){return new class{public function getSchema($ref){return \MediaWiki\MediaWikiServices::$schema;}};}
    }
}
namespace {
    require dirname(__DIR__).'/tests/standalone/autoload.php';
    use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyCore,PropertyDefinitions,PropertyName};
    use ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty;
    use ProfessionalWiki\SWS\Domain\Subject\{StatementList,SubjectMap};
    use ProfessionalWiki\SWS\Domain\Statement;
    use ProfessionalWiki\SWS\Domain\Value\StringValue;
    use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
    use ProfessionalWiki\SWS\Tests\Data\TestSubject;
    use ProfessionalWiki\SWS\EntryPoints\Processing\CategoryAssignments;
    use ProfessionalWiki\SWS\Application\Semantic\{FieldSnapshot,AnnotationPlanner};
    $n=0;
    function check($yes,$message) { global $n; $n++; if (!$yes) throw new \RuntimeException($message); }
    $core=new PropertyCore('',false,null);
    $category=PageProperty::fromPartialJson($core,['pageNamespace'=>14,'assignCategories'=>true,'multiple'=>true]);
    check($category->assignsCategories() && $category->toJson()['assignCategories']===true,'Flag roundtrip');
    check(!PageProperty::fromPartialJson($core,['pageNamespace'=>14])->assignsCategories(),'Legacy default');
    foreach ([['assignCategories'=>true],['pageNamespace'=>0,'assignCategories'=>true],['pageNamespace'=>14,'assignCategories'=>'true']] as $bad) {
        try { PageProperty::fromPartialJson($core,$bad); check(false,'Invalid schema accepted'); } catch (\InvalidArgumentException) { check(true,'Invalid schema refused'); }
    }
    function schema($category) { return new Schema(new SchemaName('Person'),'',new PropertyDefinitions(['Kategorien'=>$category]),false); }
    function subject(...$names) { return TestSubject::build(statements:new StatementList([new Statement(new PropertyName('Kategorien'),'page',new StringValue(...$names))])); }
    $schema=schema($category); $main=subject('Kategorie:Goethe','Kategorie:Familie','Kategorie:Goethe');
    $out=new \MediaWiki\Parser\ParserOutput; $out->addCategory('Goethe','Eigener Sortierschlüssel'); $out->addCategory('Wikipedia','Sortierung');
    $apply=static function($out,$schema,$main) { (new CategoryAssignments)->apply($out,$schema,new PageSubjects($main,new SubjectMap),$main,static fn()=>throw new \RuntimeException('Unexpected schema lookup')); };
    $apply($out,$schema,$main);
    check($out->categories===['Goethe'=>'Eigener Sortierschlüssel','Wikipedia'=>'Sortierung','Familie'=>''],'Merge, deduplication and original sort keys');
    $fresh=new \MediaWiki\Parser\ParserOutput; $fresh->addCategory('Goethe','Eigener Sortierschlüssel'); $apply($fresh,$schema,subject());
    check($fresh->categories===['Goethe'=>'Eigener Sortierschlüssel'],'Abwahl preserves source category, removes schema-only categories on fresh parse');
    $plain=new \MediaWiki\Parser\ParserOutput; $apply($plain,schema(PageProperty::fromPartialJson($core,['pageNamespace'=>14])),$main);
    check($plain->categories===[],'Plain page links must not categorize');
    $invalid=new \MediaWiki\Parser\ParserOutput;
    $apply($invalid,$schema,subject('Kategorie:Gueltig','Artikel'));
    check($invalid->categories===['Gueltig'=>'','SWS_Migration_Kategorie_ungültig'=>''],'Invalid legacy category marks page and retains valid assignment without aborting');
    $type=new \ProfessionalWiki\SWS\Domain\PropertyType\Types\PageType;
    check(count($type->validate(new StringValue('Artikel'),$category))===1,'Write validation restricts category namespace');
    check($type->validate(new StringValue('Kategorie:Goethe'),$category)===[],'Valid category write');
    $result=FieldSnapshot::reader($schema,$main,static fn()=>[])->collectForAnnotations();
    $plan=(new AnnotationPlanner(['properties'=>[]]))->build($result);
    check($plan->rows===[] && $plan->objects===[],'No SMW projection for category field');
    $plainResult=FieldSnapshot::reader(schema(PageProperty::fromPartialJson($core,['pageNamespace'=>14])),$main,static fn()=>[])->collectForAnnotations();
    check(count((new AnnotationPlanner(['properties'=>[]]))->build($plainResult)->rows)===2,'Normal page field keeps existing SMW behavior');
    $child=TestSubject::build(id:'s22222222222222',statements:new StatementList([new Statement(new PropertyName('Kategorien'),'page',new StringValue('Kategorie:Abhaengig'))]));
    $relation=new \ProfessionalWiki\SWS\Domain\Relation\Relation(new \ProfessionalWiki\SWS\Domain\Relation\RelationId('r11111111111111'),$child->id);
    $parent=TestSubject::build(statements:new StatementList([new Statement(new PropertyName('Kind'),'relation',new \ProfessionalWiki\SWS\Domain\Value\RelationValue($relation))]));
    foreach ([true,false] as $dependent) {
        $childSchema=new Schema(new SchemaName('Kind'),'',new PropertyDefinitions(['Kategorien'=>$category]),$dependent);
        $output=new \MediaWiki\Parser\ParserOutput;
        (new CategoryAssignments)->apply($output,$schema,new PageSubjects($parent,new SubjectMap($child)),$parent,static fn()=>$childSchema);
        check($output->categories===($dependent ? ['Abhaengig'=>''] : []),'Only dependent same-page subjects categorize host');
    }
    // Normal JSON/Lua-style rendering uses the supplied slot snapshot and reading identity.
    \MediaWiki\MediaWikiServices::$schema=$schema;
    $page=\MediaWiki\Title\Title::newFromText('Metadaten/JSON');
    $params=new \MediaWiki\Content\Renderer\ContentParseParams($page);
    $content=new \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent(new PageSubjects($main,new SubjectMap));
    $out=new \MediaWiki\Parser\ParserOutput;$out->addCategory('Goethe','Originalsortierung');
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($content,$params,$out);
    check($out->categories===['Goethe'=>'Originalsortierung','Familie'=>''],'Non-wikitext view includes slot categories and keeps existing sort');
    check(in_array('swsAccessClass',$out->usedOptions,true),'View output records access-class cache variation');
    check(count($out->dependencies)===1,'View output records schema dependency');
    $emptyContent=new \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent(new PageSubjects(subject(),new SubjectMap));
    $out=new \MediaWiki\Parser\ParserOutput;
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($emptyContent,$params,$out);
    check($out->categories===[],'View reads supplied empty snapshot, not some current subject');
    \MediaWiki\MediaWikiServices::$readAllowed=false;$out=new \MediaWiki\Parser\ParserOutput;
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($content,$params,$out);
    check($out->categories===[],'Denied viewer receives no category metadata');
    \MediaWiki\MediaWikiServices::$readAllowed=true;
    $page->model='wikitext';$out=new \MediaWiki\Parser\ParserOutput;
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($content,$params,$out);
    check($out->categories===[],'Wikitext keeps existing category rendering path');
    $page->model='json';$out=new \MediaWiki\Parser\ParserOutput;
    (new \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext($page))->run(static function()use($content,$params,$out){
        \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($content,$params,$out);
    });
    check($out->categories===[],'Worker slot leaves category application to merged full output');
    \MediaWiki\MediaWikiServices::$schema=null;$out=new \MediaWiki\Parser\ParserOutput;
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories($content,$params,$out);
    check($out->categories===[] && $out->expiry===0 && \MediaWiki\Logger\LoggerFactory::$warnings===1,'Unavailable schema yields no cached partial metadata');
    // 0.3.01: category defaults are inherited dynamically only for empty subject fields.
    $withDefault=PageProperty::fromPartialJson(new PropertyCore('',false,['AufEinWort:Schema Personendaten']),['pageNamespace'=>14,'assignCategories'=>true]);
    check($withDefault->getDefault()===['Kategorie:AufEinWort:Schema Personendaten'],'Default is qualified in selected category namespace, including colon-containing names');
    $defaultSchema=schema($withDefault);$fallback=new \MediaWiki\Parser\ParserOutput;$apply($fallback,$defaultSchema,subject());
    check($fallback->categories===['AufEinWort:Schema_Personendaten'=>''],'Empty existing subject inherits schema category');
    $fallback=new \MediaWiki\Parser\ParserOutput;$apply($fallback,$defaultSchema,subject('   '));
    check($fallback->categories===['AufEinWort:Schema_Personendaten'=>''],'Whitespace-only category field inherits schema category');
    $explicit=new \MediaWiki\Parser\ParserOutput;$apply($explicit,$defaultSchema,subject('Kategorie:Eigene Auswahl'));
    check($explicit->categories===['Eigene_Auswahl'=>''],'Explicit subject values override category defaults rather than union');
    $nextDefault=PageProperty::fromPartialJson(new PropertyCore('',false,['Kategorie:Neuer Standard']),['pageNamespace'=>14,'assignCategories'=>true]);
    $fresh=new \MediaWiki\Parser\ParserOutput;$apply($fresh,schema($nextDefault),subject());
    check($fresh->categories===['Neuer_Standard'=>''],'Schema change updates inherited category on fresh processing');
    $main=subject();$originalStatements=$main->getStatements()->asArray();$fresh=new \MediaWiki\Parser\ParserOutput;$apply($fresh,$defaultSchema,$main);
    check($main->getStatements()->asArray()===$originalStatements,'Inheritance does not turn defaults into own statements');
    $many=PageProperty::fromPartialJson(new PropertyCore('',false,['Kategorie:Eins','Zwei','Kategorie:Eins',' ']),['pageNamespace'=>14,'assignCategories'=>true,'multiple'=>true]);
    check($many->getDefault()===['Kategorie:Eins','Kategorie:Zwei'],'Multiple defaults normalize and deduplicate without duplicate prefix');
    foreach (['https://example.org','Kategorie:Test#Abschnitt','w:Seite'] as $bad) {
        try { PageProperty::fromPartialJson(new PropertyCore('',false,[$bad]),['pageNamespace'=>14,'assignCategories'=>true]);check(false,'Invalid default accepted'); } catch (\InvalidArgumentException) { check(true,'Unsafe category default rejected'); }
    }
    $plainDefault=PageProperty::fromPartialJson(new PropertyCore('',false,['AufEinWort:Schema Personendaten']),['pageNamespace'=>14]);
    check($plainDefault->getDefault()===['AufEinWort:Schema Personendaten'],'Ordinary page-link defaults are not forced into category assignment');
    \MediaWiki\MediaWikiServices::$schema=$defaultSchema;
    $out=new \MediaWiki\Parser\ParserOutput;
    \ProfessionalWiki\SWS\EntryPoints\Processing\SubjectSlotMetadata::addCategories(new \ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent(new PageSubjects(subject(),new SubjectMap)),$params,$out);
    check($out->categories===['AufEinWort:Schema_Personendaten'=>''],'Non-wikitext slot inherits same schema category');
    echo "$n category schema/validation/merge/removal/SMW checks passed (isolated MediaWiki stand-ins).\n";
}
