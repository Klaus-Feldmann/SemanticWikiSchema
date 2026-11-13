<?php

declare(strict_types=1);
require __DIR__.'/source-refresh/CollectorHost.php';
require __DIR__.'/source-refresh/StoreReadHost.php';
require (getenv('SWS_TEST_VENDOR') ?: dirname(__DIR__).'/vendor/autoload.php');
// Core-only installation: deliberately do not register the optional bridge's namespace.
spl_autoload_register(static function(string $class): void {
 foreach(['ProfessionalWiki\\SWS\\Tests\\'=>'tests/phpunit/','ProfessionalWiki\\SWS\\'=>'src/'] as $prefix=>$path){
  if(str_starts_with($class,$prefix)){ $file=dirname(__DIR__).'/'.$path.str_replace('\\','/',substr($class,strlen($prefix))).'.php';if(is_file($file))require $file; }
 }
},true,true);
use CollectorHarness\State as C;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\{TestSources,TestSubjectIds};
use ProfessionalWiki\SWS\Domain\Schema\{PropertyDefinition,PropertyDefinitions,Schema,SchemaName,SchemaReference,PropertyName};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList,SubjectMap};
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\EffectiveFieldLookup;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\{SubjectContentDataDeserializer,StatementDeserializer};
$n=0;function coreCheck(bool $ok,string $why):void{global $n;$n++;if(!$ok)throw new RuntimeException($why);}
function wfMessage(string $key,mixed ...$args):object{return new class($key){public function __construct(private string $key){} public function inContentLanguage():self{return $this;} public function text():string{return $this->key;}};}
$registry=TestSources::newPropertyTypeRegistry();$props=[];$fields=[];
foreach(['Ort'=>'wikidata','Lokaler Ort'=>'wikibase-local'] as $name=>$source){
 $props[$name]=PropertyDefinition::fromJson(['type'=>'text','externalProperty'=>['source'=>$source,'id'=>'P19']],$registry);
 $fields[]=['name'=>$name,'propertyType'=>'text','source'=>$source,'propertyId'=>'P19','entityId'=>'Q1','status'=>'resolved','values'=>[['kind'=>'text','value'=>'Ort aus '.$source]]];
}
$props['Name']=PropertyDefinition::fromJson(['type'=>'text'],$registry);
SWSExtension::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions($props),false);
SWSExtension::$deserializer=new SubjectContentDataDeserializer(new StatementDeserializer($registry,TestSubjectIds::newParser()),new Psr\Log\NullLogger(),TestSources::newSchemaReferenceParser());
$id=new SubjectId('s11111111111111');$snapshot=SourceSnapshot::recordAttempt($id,943,101,[901=>100],'de','2026-11-12T10:00:00Z',$fields,[]);
$subject=new Subject($id,null,SchemaReference::local(new SchemaName('Person')),new StatementList([new Statement(new PropertyName('Name'),'text',new StringValue('Eigene Angabe'))]),[],$snapshot);
C::$revision=new MediaWiki\Revision\RevisionRecord(102,943,['sws'=>SubjectContent::newFromData(new PageSubjects($subject,new SubjectMap()))]);C::$revisions[102]=C::$revision;
coreCheck(!class_exists(SWSBaseBridge\StoredSourceLookup::class),'Optional bridge really absent');
$result=(new EffectiveFieldLookup(new MediaWiki\Context\TestContext()))->read(new MediaWiki\Title\Title());$f=array_column($result['fields'],null,'name');
coreCheck($f['Ort']['values'][0]['value']==='Ort aus wikidata','Stored public source value readable without bridge');
coreCheck($f['Name']['values'][0]['value']==='Eigene Angabe','Own data readable without bridge or SMW');
coreCheck($f['Lokaler Ort']['values']===[]&&$f['Lokaler Ort']['external']['reason']==='source-bridge-unavailable','Local source withheld without permission revalidation capability');
coreCheck(C::$requests===[]&&C::$identityLookups===0,'Core-only reads make no source requests');
echo "$n core-only field read checks passed without source bridge or SMW (controlled host).\n";
