<?php
// Exercise the actual planner and SMW adapter. SMW handlers/storage and MediaWiki are stand-ins.
namespace MediaWiki\Title {
    class Title {
        public function __construct(public string $name,public string $model='json') {}
        public function getContentModel(){return $this->model;}
        public function isSamePageAs($title){return $this->name===$title->name;}
        public function canExist(){return true;}
        public static function makeTitleSafe($ns,$name){return new self($ns.':'.$name);}
        public function getArticleID(){return 1;}
        public function getLatestRevID(){return 1;}
    }
}
namespace MediaWiki\Parser {
    class ParserOutput {
        public array $data=[], $dependencies=[];
        public function getExtensionData($key){return $this->data[$key]??null;}
        public function setExtensionData($key,$value){$this->data[$key]=$value;}
        public function addTemplate($title,...$args){$this->dependencies[]=$title->name;}
    }
    class ParserOptions { public static function newFromUser($user){return new self;} }
    class Parser {
        public const OT_HTML=1;
        public ParserOutput $output;
        public function __construct(private $title=null){$this->output=new ParserOutput;}
        public function startExternalParse($title,...$args){$this->title=$title;}
        public function getTitle(){return $this->title;}
        public function getOutput(){return $this->output;}
        public function parse(...$args){throw new \RuntimeException('Main content must never be parsed as wikitext');}
    }
}
namespace MediaWiki\User { class User { public static function newSystemUser(...$args){return new self;} } }
namespace MediaWiki\Context {
    class RequestContext { public static function getMain(){return new self;} }
    class DerivativeContext {public function __construct($context){}public function setUser($user){} }
}
namespace SMW\DataModel {
    class SemanticData {
        public array $rows=[];
        public function importDataFrom($other){$this->rows=array_values(array_unique([...$this->rows,...$other->rows]));}
    }
}
namespace SMW {
    class ParserData {
        private $data;
        public function __construct($title,private $output){$this->data=$output->getExtensionData('smwdata')??new \SMW\DataModel\SemanticData; $output->setExtensionData('smwdata',$this->data);}
        public function canUse(){return !$this->output->getExtensionData('smw-blockannotation');}
        public function getSemanticData(){return $this->data;}
    }
    class ParserParameterProcessor {public function __construct(public array $args){} }
}
namespace MediaWiki {
    class MediaWikiServices {
        public static function getInstance(){return new self;}
        public function getContentLanguage(){return new class {public function getCode(){return 'de';}};}
        public function getMainConfig(){return new class {public function get($key){if($key==='SWSSourceDataMode')return 'store';if($key!=='WikiSchemaSmwMapping')throw new \RuntimeException($key);return [];}};}
        public function getParserFactory(){return new class {public function create(){return new \MediaWiki\Parser\Parser;}};}
        public function getService($name){
            if($name!=='SMW.ParserFunctionFactory')throw new \RuntimeException($name);
            return new class {
                public function newSetParserFunction($parser){return new class($parser){
                    public function __construct(private $parser){}
                    public function parse($args){$data=(new \SMW\ParserData($this->parser->getTitle(),$this->parser->getOutput()))->getSemanticData();array_push($data->rows,...$args->args);}
                };}
                public function newSubobjectParserFunction($parser){throw new \RuntimeException('Unexpected subobject');}
            };
        }
    }
}
namespace ProfessionalWiki\SWS { class SWSExtension {public const NS_SCHEMA=7474;} }
namespace {
    define('SMW_NS_PROPERTY',102);
    require dirname(__DIR__).'/tests/standalone/autoload.php';
    use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyCore,PropertyDefinitions,PropertyName};
    use ProfessionalWiki\SWS\Domain\Schema\Property\TextProperty;
    use ProfessionalWiki\SWS\Domain\Subject\{StatementList,SubjectMap};
    use ProfessionalWiki\SWS\Domain\{Statement};
    use ProfessionalWiki\SWS\Domain\Value\StringValue;
    use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
    use ProfessionalWiki\SWS\Tests\Data\TestSubject;
    use ProfessionalWiki\SWS\EntryPoints\Semantic\AutomaticAnnotations;
    use MediaWiki\Parser\{Parser,ParserOutput};
    use MediaWiki\Title\Title;
    $checks=0;function check($yes,$why){global $checks;$checks++;if(!$yes)throw new \RuntimeException($why);}
    $schema=new Schema(new SchemaName('Verwaltung'),'',new PropertyDefinitions(['Status'=>new TextProperty(new PropertyCore('',false,null),false,false,null,null)]),false);
    function subject($value){return TestSubject::build(schemaName:new SchemaName('Verwaltung'),statements:new StatementList([new Statement(new PropertyName('Status'),'text',new StringValue($value))]));}
    $user=new \MediaWiki\User\User;
    function apply($title,$parser,$schema,$subject){global $user;$out=new ParserOutput;(new AutomaticAnnotations)->apply($title,$out,$parser,$schema,new PageSubjects($subject,new SubjectMap),$subject,$user,static fn()=>$schema);return $out;}
    foreach(['Scribunto','json','javascript','css']as $model){
        $title=new Title('Metadaten/'.$model,$model);
        $out=apply($title,null,$schema,subject('geprüft'));
        check($out->getExtensionData('smwdata')->rows===['Verwaltung:Status=geprüft'],'Non-wikitext annotation without main parser: '.$model);
        check(count($out->dependencies)===2,'Schema and property dependencies: '.$model);
        $out=apply($title,null,$schema,subject('geändert'));
        check($out->getExtensionData('smwdata')->rows===['Verwaltung:Status=geändert'],'Fresh output replaces previous value: '.$model);
        $out=apply($title,null,$schema,subject(''));
        check($out->getExtensionData('smwdata')->rows===[],'Fresh empty field removes previous annotation: '.$model);
    }
    $title=new Title('Vorlage:Metadaten','wikitext');
    check(apply($title,new Parser($title),$schema,subject('Vorlage'))->getExtensionData('smwdata')->rows===['Verwaltung:Status=Vorlage'],'Wikitext path retained');
    foreach([[$title,null],[new Title('Modul:Metadaten','Scribunto'),new Parser(new Title('Andere Seite'))]]as [$title,$parser]){
        try{apply($title,$parser,$schema,subject('abgelehnt'));check(false,'Invalid parser context accepted');}catch(\ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete){check(true,'Incomplete/wrong parser remains refused');}
    }
    echo "$checks non-wikitext annotation/change/removal/context checks passed (isolated MediaWiki/SMW stand-ins).\n";
}
