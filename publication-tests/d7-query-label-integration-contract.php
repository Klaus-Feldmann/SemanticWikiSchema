<?php
require __DIR__.'/d5-query-scope-contract.php';
use CollectorHarness\State as D7;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\EntryPoints\{SWSValueParserFunction,ParserFieldLookup};
use MediaWiki\Parser\Parser;
$wire=json_decode(file_get_contents(__DIR__.'/fixtures/d7-schema.json'),true);
$wire['propertyDefinitions']['F4']['externalProperty']=['source'=>'wikidata','id'=>'P569'];
$before=serialize($ea->getStatements());
$resolver=new class($ea,$eb) extends \ProfessionalWiki\SWS\Application\SubjectResolver {
 public function __construct(private \ProfessionalWiki\SWS\Domain\Subject\Subject $first,private \ProfessionalWiki\SWS\Domain\Subject\Subject $second){}
 public function resolveById(string $id): ?\ProfessionalWiki\SWS\Domain\Subject\Subject{return match($id){'E31'=>$this->first,'E32'=>$this->second,default=>null};}
 public function resolveMainByTitle(\MediaWiki\Title\Title $title): ?\ProfessionalWiki\SWS\Domain\Subject\Subject{return $this->first;}
 public function resolveDisplayValue(\ProfessionalWiki\SWS\Domain\Subject\Subject $subject,\ProfessionalWiki\SWS\Domain\Statement $statement): \ProfessionalWiki\SWS\Domain\Value\NeoValue{return $statement->getValue();}
};
foreach(['de','en'] as $language){
 $GLOBALS['wgLanguageCode']=$language;
 foreach([false,true] as $reordered){
  $data=$wire;
  if($reordered)$data['propertyDefinitions']=array_reverse($data['propertyDefinitions'],true);
  $schema=(new SchemaPersistenceDeserializer($registry))->deserialize(new SchemaName('S11'),json_encode($data));
  SWSExtension::$schemaById['S11']=$schema;
  $saved=(new SchemaPresentationSerializer())->toArray($schema);
  xcheck($saved['propertyDefinitions']['F4']['labels']===$wire['propertyDefinitions']['F4']['labels'],'Persistence retains multilingual map');
  xcheck(array_keys($saved['propertyDefinitions'])===array_keys($data['propertyDefinitions']),'Persistence retains chosen order without renumbering');
  foreach(['store','cache']as$mode){
   D7::$mode=$mode;D7::$requests=[];
   $parser=new Parser();$query=new SWSValueParserFunction($resolver,ParserFieldLookup::create($parser));
   foreach([[],['origin=own']]as$options){
    $requestsBefore=count(D7::$requests);
    $result=$query->handle($parser,'E31','F4',...$options);
    if($options!==[])xcheck(count(D7::$requests)===$requestsBefore,'Own-only query adds no external requests');
    xcheck(($result[0]??null)==='Schema A','Own data remains queryable despite labels, order and source binding');
    xcheck($result['isHTML']===false && $result['noparse']===true,'Result composes with caller Wikitext without strip markers');
   }
   xcheck($query->handle($parser,'E31','Geburtsdatum')==='','German caption is not a second identity');
   xcheck($query->handle($parser,'E31','Date of birth')==='','English caption is not a second identity');
   xcheck($query->handle($parser,'E32','F4')[0]==='42','Other schema F4 retains its independent datatype');
   if($mode==='store')xcheck(D7::$requests===[],'Store remains network-free');
  }
 }
}
xcheck(serialize($ea->getStatements())===$before,'Labels and reordering leave entity statements untouched');
D7::$mode='store';
echo "$count D7 query/persistence/label/order/store/cache integration checks passed (controlled host).\n";
