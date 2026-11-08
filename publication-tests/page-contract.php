<?php
// Isolated behavior contract. MediaWiki services are stand-ins; live validation follows installation.
namespace MediaWiki\Title {
 final class Title {
  private function __construct(private string $text){}
  public static function newFromText($text){
   if(trim($text)===''||preg_match('/[\[\]{}|<>]/',$text))return null;
   return new self(ucfirst(str_replace('_',' ',trim($text))));
  }
  public function isExternal(){return str_starts_with($this->text,'W:')||str_starts_with($this->text,'Wikipedia:');}
  public function canExist(){return !str_starts_with($this->text,'Special:')&&!str_starts_with($this->text,'Media:');}
  public function getPrefixedText(){return $this->text;}
  public function getFullURL(){return 'https://wiki.example/wiki/'.rawurlencode(str_replace(' ','_',$this->text));}
 }
}
namespace MediaWiki {
 final class MediaWikiServices {
  public static function getInstance(){return new self;}
  public function getNamespaceInfo(){return new class{public function getCanonicalNamespaces(){return [0=>'',102=>'Werk'];}};}
 }
}
namespace {
 require dirname(__DIR__).'/tests/standalone/autoload.php';
 use ProfessionalWiki\SWS\Domain\PropertyType\Types\PageType;
 use ProfessionalWiki\SWS\Domain\PropertyType\Types\LocalPageTitle;
 use ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty;
 use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
 use ProfessionalWiki\SWS\Domain\Value\StringValue;
 use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
 $n=0;function check($ok,$why){global $n;$n++;if(!$ok)throw new \RuntimeException($why);}
 $core=new PropertyCore('',false,null);
 $p=PageProperty::fromPartialJson($core,['pageNamespace'=>102,'multiple'=>true,'uniqueItems'=>true]);
 $type=new PageType;
 check($p->getPropertyType()==='page'&&$p->getPageNamespace()===102,'Page type/namespace lost');
 check($p->toJson()['pageNamespace']===102,'Namespace not serialized');
 check($p->allowsMultipleValues()&&$p->enforcesUniqueValues(),'Text-like settings lost');
 check($type->validate(new StringValue('Athen','Neue Seite'),$p)===[],'Valid/missing titles rejected');
 check(LocalPageTitle::normalize('  neue_Seite  ')==='Neue Seite','Title not normalized');
 check(LocalPageTitle::normalize('Werk:Abhandlung')==='Werk:Abhandlung','Namespace lost');
 foreach(['https://example.org','Special:RecentChanges','Media:Photo.jpg','w:Article','bad[title','Athen#Antike',"Athen\nStadt"]as $bad){
  check(LocalPageTitle::normalize($bad)===null,'Bad title accepted: '.$bad);
  check(count($type->validate(new StringValue($bad),$p))>=1,'Bad page not reported');
 }
 check(count($type->validate(new StringValue('Athen','Athen'),$p))===1,'Duplicates not reported');
 $required=PageProperty::fromPartialJson(new PropertyCore('',true,null),[]);
 check(count($type->validate(new StringValue(),$required))===1,'Required not enforced');
 $length=PageProperty::fromPartialJson($core,['minLength'=>4,'maxLength'=>8]);
 check(count($type->validate(new StringValue('Ab'),$length))===1,'Min length lost');
 check(count($type->validate(new StringValue('Abcdefghi'),$length))===1,'Max length lost');
 foreach([-1,999,'102',1.5]as $ns){
  try{PageProperty::fromPartialJson($core,['pageNamespace'=>$ns]);check(false,'Invalid namespace accepted');}
  catch(\InvalidArgumentException){check(true,'Invalid namespace refused');}
 }
 $lookup=new class implements \ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup{
  public function getType(string $name):?\ProfessionalWiki\SWS\Domain\PropertyType\PropertyType{return $name==='page'?new PageType:null;}
 };
 $ids=new class implements \ProfessionalWiki\SWS\Infrastructure\IdGenerator{public function generate():string{return 'sTest1234567890';}};
 $builder=new \ProfessionalWiki\SWS\Application\StatementListBuilder($lookup,$ids,new \ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser('local'));
 $statements=$builder->build(['Ort'=>['propertyType'=>'page','value'=>['neue_Seite','Werk:Abhandlung']]]);
 check($statements->getStatement(new PropertyName('Ort'))->getValue()->toScalars()===['Neue Seite','Werk:Abhandlung'],'Write boundary failed normalization');
 foreach([['w:Article'],['https://example.org'],['Athen#Antike'],['key'=>'Athen']]as $bad){
  try{$builder->build(['Ort'=>['propertyType'=>'page','value'=>$bad]]);check(false,'Bad write accepted');}
  catch(\InvalidArgumentException){check(true,'Bad write refused');}
 }
 $rdf=\ProfessionalWiki\SWS\Domain\Rdf\RdfValueMapperRegistry::withCoreMappers();
 $mapped=$rdf->mapValue('page',new StringValue('Werk:Abhandlung'));
 check(count($mapped)===1&&$mapped[0] instanceof \ProfessionalWiki\SWS\Domain\Rdf\Iri,'Page not exported as IRI');
 $neo=\ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry::withCoreBuilders();
 check($neo->buildNeo4jValue('page',new StringValue('Werk:Abhandlung'))===['Werk:Abhandlung'],'Neo4j title lost');
 echo "$n page storage/validation/export checks passed (isolated MediaWiki stand-ins).\n";
}
