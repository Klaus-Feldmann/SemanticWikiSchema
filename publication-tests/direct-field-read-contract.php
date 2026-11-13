<?php

declare( strict_types = 1 );
require __DIR__ . '/source-store-read-contract.php';
require __DIR__ . '/source-refresh/DirectReadHost.php';
define('SMW_NS_PROPERTY',102);

use CollectorHarness\State as D;
use MediaWiki\Context\TestContext;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\Semantic\{ FieldSnapshot, AnnotationPlanner, FieldNoteAnnotations };
use ProfessionalWiki\SWS\Domain\Schema\{ PropertyDefinition, PropertyDefinitions, PropertyName, Schema, SchemaName };
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\{ StringValue, BooleanValue, NumberValue };
use ProfessionalWiki\SWS\EntryPoints\{ EffectiveFieldLookup, ParserFieldLookup, SWSValueParserFunction };
use ProfessionalWiki\SWS\EntryPoints\Api\ApiFieldValues;
use ProfessionalWiki\SWS\EntryPoints\Source\SourceFieldLookup;
use ProfessionalWiki\SWS\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\SWS\SWSExtension;

function wfMessage( string $key, mixed ...$args ): object {
    return new class( $key, $args ) {
        public function __construct(private string $key,private array $args) {}
        public function inContentLanguage(): self { return $this; }
        public function text(): string { return $this->key === 'sws-notes-property-suffix' ? ' (AM)' : $this->key.':'.implode(' ',$this->args); }
    };
}
$n=0;
function dcheck(bool $ok,string $why): void { global $n; $n++; if(!$ok)throw new RuntimeException($why); }
function drefuse(callable $f,string $why): void { try{$f();}catch(RuntimeException|InvalidArgumentException){dcheck(true,$why);return;}throw new RuntimeException($why); }
D::$mode='store';D::$publish=true;D::$publishedId=null;D::$denied=[];D::$requests=[];D::$identityLookups=0;
$properties['Ort']=PropertyDefinition::fromJson(['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P19'],'fieldNotes'=>['type'=>'wikitext','annotate'=>true]],$registry);
foreach(['Aktiv'=>'boolean','Anzahl'=>'number'] as $name=>$type){$properties[$name]=PropertyDefinition::fromJson(['type'=>$type],$registry);}
$properties['Datum']=PropertyDefinition::fromJson(['type'=>'historicalDate','externalProperty'=>['source'=>'wikidata','id'=>'P569']],$registry);
$properties['Bild']=PropertyDefinition::fromJson(['type'=>'image','externalProperty'=>['source'=>'wikidata','id'=>'P18'],'fieldNotes'=>['type'=>'text','annotate'=>true]],$registry);
$properties['Vorlage']=PropertyDefinition::fromJson(['type'=>'wikitext','wikitext'=>'[[Kategorie:Nicht beim Lesen setzen]]'],$registry);
SWSExtension::$schema=new Schema(new SchemaName('Person'),' ',new PropertyDefinitions($properties),false);
$time=['name'=>'Datum','source'=>'wikidata','propertyId'=>'P569','entityId'=>'Q41390','propertyType'=>'historicalDate','status'=>'resolved','values'=>[
 ['kind'=>'time','label'=>'28. April 1906','sourceTime'=>['time'=>'+1906-04-28T00:00:00Z','precision'=>11,'calendarmodel'=>'http://www.wikidata.org/entity/Q1985727'],'year'=>1906,'month'=>4,'day'=>28]]];
$image=['name'=>'Bild','source'=>'wikidata','propertyId'=>'P18','entityId'=>'Q41390','propertyType'=>'image','status'=>'resolved','values'=>[['kind'=>'image','reference'=>'commons:Goedel.jpg','filename'=>'Goedel.jpg','repository'=>'commons']]];
$initial=storedAttempt([storeField(),$time,$image],['wikidata'=>$identity]);
$failed=storeField();$failed['status']='unavailable';$failed['values']=[];
$snapshot=storedAttempt([$failed,$time,$image],['wikidata'=>$identity],$initial,103);
$subject=readStoreSnapshot($snapshot)->withStatements(new StatementList([
 new Statement(new PropertyName('Name'),'text',new StringValue('Eigener Name')),
 new Statement(new PropertyName('Aktiv'),'boolean',new BooleanValue(false)),
 new Statement(new PropertyName('Anzahl'),'number',new NumberValue(7))]));
setStorePage($subject);$context=new TestContext();$title=new Title();$lookup=new EffectiveFieldLookup($context);
$r=$lookup->read($title);$fields=array_column($r['fields'],null,'name');
dcheck($r['version']===1&&$r['readOnly']&&$r['sourceDataMode']==='store','Versioned read-only direct field contract');
dcheck($fields['Ort']['values'][0]['value']==='Gespeicherter Ort'&&$fields['Ort']['values'][0]['origin']==='wikidata','Effective source from local stock');
dcheck($fields['Ort']['sourceStatus']==='unavailable'&&$fields['Ort']['external']['retained'],'Failed attempt separate from retained value');
dcheck($fields['Ort']['authorNote']==='Forschungsnotiz'&&str_contains($fields['Ort']['sourceNote'],'unavailable'),'Notes separately readable');
dcheck($fields['Datum']['values'][0]['metadata']['sourceTime']['precision']===11&&$fields['Datum']['values'][0]['value']==='28. April 1906','Date precision and display preserved');
dcheck(!isset($fields['Bild'],$fields['Vorlage']),'Images optional and schema template not executed');
dcheck($lookup->read($title,['field'=>'Bild','includeImages'=>true])['fields'][0]['values'][0]['value']==='Goedel.jpg','Optional image filename');
dcheck($fields['Aktiv']['values'][0]['value']==='nein'&&$fields['Anzahl']['values'][0]['metadata']['raw']===7,'False and number remain occupied');
dcheck($lookup->read($title,['field'=>'Ort','origin'=>'own'])['fields'][0]['values']===[],'Own projection excludes source');
dcheck($lookup->read($title,['field'=>'Ort','language'=>'en'])['fields'][0]['external']['languageFallback'],'Language fallback explicit');
$api=new ApiFieldValues(['page'=>'Artikel','field'=>'Ort','language'=>'de','origin'=>'all','includeImages'=>false],$context);$api->execute();
dcheck($api->output['result']['fields'][0]===$fields['Ort']&&!$api->isWriteMode(),'API shares exact field contract');
$apiId=new ApiFieldValues(['subject'=>$subject->id->text,'field'=>'Ort','language'=>'de'],$context);$apiId->execute();
dcheck($apiId->output['result']['page']['id']===943,'Subject-only API resolves host');
drefuse(fn()=>(new ApiFieldValues(['page'=>'Artikel','subject'=>$subject->id->text],$context))->execute(),'Ambiguous API target rejected');
foreach([['language'=>'../../de'],['origin'=>'unsafe'],['unexpected'=>'yes'],['field'=>'Nonexistent']] as $invalid){drefuse(fn()=>$lookup->read($title,$invalid),'Invalid options rejected');}
dcheck($lookup->read($title,['subject'=>'external:Q1'])['missing'],'External IDs do not trigger remote requests');
D::$denied=['Artikel'];dcheck((new EffectiveFieldLookup($context))->read($title)['missing'],'Unreadable page hides fields');D::$denied=[];
$parser=new Parser();$parserFields=ParserFieldLookup::create($parser);
$legacy=new class($subject) extends \ProfessionalWiki\SWS\Application\SubjectResolver {
 public function __construct(private mixed $subject) {}
 public function resolveMainByTitle(Title $title): ?\ProfessionalWiki\SWS\Domain\Subject\Subject { return $this->subject; }
 public function resolveById(string $id): ?\ProfessionalWiki\SWS\Domain\Subject\Subject { return $id===$this->subject->id->text?$this->subject:null; }
 public function resolveDisplayValue(\ProfessionalWiki\SWS\Domain\Subject\Subject $subject,\ProfessionalWiki\SWS\Domain\Statement $statement): \ProfessionalWiki\SWS\Domain\Value\NeoValue { return $statement->getValue(); }
};
SWSExtension::$resolver=$legacy;
$function=new SWSValueParserFunction($legacy,$parserFields);
dcheck($function->handle($parser,'Ort')[0]==='Gespeicherter Ort','Parser effective value');
dcheck($function->handle($parser,'Ort','part=authorNote')[0]==='Forschungsnotiz','Parser author note');
dcheck(str_contains($function->handle($parser,'Ort','part=sourceNote')[0],'unavailable'),'Parser source note');
dcheck($function->handle($parser,'Ort','part=origin')[0]==='wikidata'&&$function->handle($parser,'Ort','part=sourceStatus')[0]==='unavailable','Parser origin and status');
dcheck(in_array('swsAccessClass',D::$parserOptions,true)&&in_array('Schema:Person',D::$dependencies,true),'Parser authority class and dependency');
$lua=new ScribuntoLuaLibrary($parser);
dcheck($function->handle($parser,'Ort','origin=own')===''&&$function->handle($parser,'Name','origin=own')[0]==='Eigener Name','Parser explicit own path preserved');
dcheck($lua->getValue('Ort',['origin'=>'own'])===[null]&&$lua->getValue('Name',['origin'=>'own'])===['Eigener Name'],'Lua explicit own path preserved');
dcheck($lua->getValue('Ort')===['Gespeicherter Ort'],'Lua effective scalar');
dcheck($lua->getAll('Ort')===[[1=>'Gespeicherter Ort']],'Lua one-based list');
dcheck($lua->getValue('Aktiv')===[false]&&$lua->getValue('Anzahl')===[7],'Lua boolean and number types');
$lf=$lua->collectFields(['field'=>'Ort'])[0];
dcheck($lf['fields'][1]['values'][1]['metadata']['kind']==='text'&&$lf['fields'][1]['authorNote']==='Forschungsnotiz','Lua typed fields and notes');
$view=(new \SWSBaseBridge\DataViewLookup($context))->read($title,'de');$vf=array_column($view['subjects'][0]['fields'],null,'name');
dcheck($vf['Ort']['effectiveValues'][0]['value']===$fields['Ort']['values'][0]['value'],'View and direct field agree');
$reader=FieldSnapshot::reader(SWSExtension::$schema,$subject,fn()=>SourceFieldLookup::read($context,$title,$subject,SWSExtension::$schema,'de'));
$smw=$reader->collectForAnnotations();$plan=(new AnnotationPlanner(['properties'=>[]]))->build($smw);
dcheck(in_array('Ort=Gespeicherter Ort',$plan->rows,true)&&!str_contains(implode('\n',$plan->rows),'unavailable'),'SMW uses value not error');
$notes=(new FieldNoteAnnotations())->build($smw,['properties'=>[]]);
dcheck(in_array('Ort (AM)=Forschungsnotiz',$notes,true)&&count(array_filter($notes,fn($v)=>str_contains($v,'unavailable')))===1,'SMW separate notes once');

$user=new \MediaWiki\User\User();
(new \ProfessionalWiki\SWS\EntryPoints\Semantic\AutomaticAnnotations())->apply($title,new \MediaWiki\Parser\ParserOutput([]),$parser,SWSExtension::$schema,
 new \ProfessionalWiki\SWS\Domain\Page\PageSubjects($subject,new \ProfessionalWiki\SWS\Domain\Subject\SubjectMap()),$subject,$user,fn()=>SWSExtension::$schema);
$automatic=\ProfessionalWiki\SWS\EntryPoints\Semantic\SmwAnnotationApplier::$plan;
dcheck(in_array('Ort=Gespeicherter Ort',$automatic->rows,true)&&in_array('Ort (AM)=Forschungsnotiz',$automatic->rows,true),'Actual AutomaticAnnotations uses shared local reader and note planner');
$manual=$subject->withStatements(new StatementList([new Statement(new PropertyName('Ort'),'text',new StringValue('Forschungsstand'))]));setStorePage($manual);
$m=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort'])['fields'][0];
dcheck($m['values'][0]['value']==='Forschungsstand'&&$m['values'][0]['origin']==='own'&&$m['sourceStatus']==='unavailable','Own wins without hiding source status');
dcheck((new EffectiveFieldLookup($context))->read($title,['field'=>'Ort','origin'=>'external'])['fields'][0]['values'][0]['value']==='Gespeicherter Ort','External projection available despite manual override');
$subject->setSourceSnapshot(null);setStorePage($subject);
$pending=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort'])['fields'][0];
dcheck($pending['values']===[]&&$pending['external']['recorded']===false&&$pending['sourceNote']===null,'Pending distinct from source failure note');

// A field query does not turn free text into executable Wikitext or HTML.
$properties['Freitext']=PropertyDefinition::fromJson(['type'=>'wikitextValue'],$registry);
SWSExtension::$schema=new Schema(new SchemaName('Person'),' ',new PropertyDefinitions($properties),false);
$multi=$subject->withStatements(new StatementList([
 new Statement(new PropertyName('Name'),'text',new StringValue('Erster','Zweiter')),
 new Statement(new PropertyName('Freitext'),'wikitextValue',new StringValue('<script>unsafe</script>[[Kategorie:Test]]'))]));
setStorePage($multi);$mp=new Parser();$ml=new ScribuntoLuaLibrary($mp);
dcheck($ml->getAll('Name')===[[1=>'Erster',2=>'Zweiter']],'All scalar values returned as Lua list');
$mf=new SWSValueParserFunction($legacy,ParserFieldLookup::create($mp));
dcheck($mf->handle($mp,'Name','separator= / ')[0]==='Erster/Zweiter','Parser separator controls multiple values');
$escaped=$mf->handle($mp,'Freitext');
dcheck(str_contains($escaped[0],'&lt;script&gt;')&&$escaped['noparse']&&$escaped['isHTML'],'Free Wikitext remains escaped in scalar query output');
$pin=new EffectiveFieldLookup($context);$before=$pin->read($title,['field'=>'Name']);
setStorePage($manual,104);
dcheck($pin->read($title,['field'=>'Name'])['fields'][0]['values']===$before['fields'][0]['values'],'One reader retains one published snapshot despite later revision');
dcheck((new EffectiveFieldLookup($context))->read($title,['field'=>'Name'])['fields'][0]['values']===[],'New reader sees later revision');

$localProperties=$properties;
$localProperties['Ort']=PropertyDefinition::fromJson(['type'=>'text','externalProperty'=>['source'=>'wikibase-local','id'=>'P19']],$registry);
SWSExtension::$schema=new Schema(new SchemaName('Person'),' ',new PropertyDefinitions($localProperties),false);
readStoreSnapshot(storedAttempt([storeField('Ort','wikibase-local','Q1')],[]));D::$denied=['P19'];
$private=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort']);
dcheck($private['fields'][0]['values']===[]&&$private['fields'][0]['external']['reason']==='source-not-readable','Direct local Wikibase permission redaction');
dcheck(!str_contains(json_encode($private['fields']),'Gespeicherter Ort'),'Private source labels and last-attempt data not exposed');D::$denied=[];
// Source-only image warnings must also be available to the annotation projection.
SWSExtension::$schema=new Schema(new SchemaName('Person'),' ',new PropertyDefinitions(['Bild'=>$properties['Bild']]),false);
$badImage=$image;$badImage['status']='unavailable';$badImage['values']=[];
$imageSubject=readStoreSnapshot(storedAttempt([$badImage],[], $initial,103));
(new \ProfessionalWiki\SWS\EntryPoints\Semantic\AutomaticAnnotations())->apply($title,new \MediaWiki\Parser\ParserOutput([]),$parser,SWSExtension::$schema,
 new \ProfessionalWiki\SWS\Domain\Page\PageSubjects($imageSubject,new \ProfessionalWiki\SWS\Domain\Subject\SubjectMap()),$imageSubject,$user,fn()=>SWSExtension::$schema);
$imagePlan=\ProfessionalWiki\SWS\EntryPoints\Semantic\SmwAnnotationApplier::$plan;
dcheck(count($imagePlan->rows)===1&&str_starts_with($imagePlan->rows[0],'Bild (AM)=')&&str_contains($imagePlan->rows[0],'unavailable'),'Image-only source warning from local stock without scalar image annotation');
dcheck(D::$requests===[]&&D::$identityLookups===0,'Zero D4 store source/identity requests');

// Optional cache operation still resolves sources; an own-only projection never does.
SWSExtension::$schema=new Schema(new SchemaName('Person'),' ',new PropertyDefinitions(['Ort'=>$localProperties['Ort']]),false);
readStoreSnapshot(storedAttempt([storeField('Ort','wikibase-local','Q500')],[]));
D::$mode='cache';D::$cache=[];D::$failIds=[];
$ownOnly=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort','origin'=>'own']);
dcheck(D::$requests===[]&&$ownOnly['fields'][0]['values']===[],'Own-only cache projection makes no source request');
$cacheResult=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort']);
dcheck(count(D::$requests)>0&&$cacheResult['fields'][0]['values'][0]['value']==='Cache-Abfrage','Direct cache projection uses configured previous source resolver');
D::$mode='store';$beforeRequests=count(D::$requests);
$backToStore=(new EffectiveFieldLookup($context))->read($title,['field'=>'Ort']);
dcheck(count(D::$requests)===$beforeRequests&&$backToStore['fields'][0]['values'][0]['value']==='Gespeicherter Ort','Switch back to store reads preserved stock without requests');

// SWS records its own lookup errors explicitly, rather than inspecting foreign HTML.
$ownContext=new \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext($title);
$ownContext->run(function()use($lua,$ownContext){
 try{$lua->collectFields(['field'=>'Nicht vorhandenes SWS-Feld']);throw new LogicException('Lookup should fail');}
 catch(\MediaWiki\Extension\Scribunto\Engines\LuaCommon\LuaError){dcheck($ownContext->failure!==null,'SWS Lua lookup failure explicitly recorded by SWS');}
});
$ownContext=new \ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext($title);
$ownContext->run(function()use($mf,$mp,$ownContext){
 dcheck($mf->handle($mp,'Nicht vorhandenes SWS-Feld')===''&&$ownContext->failure!==null,'SWS parser function explicitly records own lookup failure');
});
echo "$n D4 direct/API/parser/Lua/SMW consistency checks passed (controlled host, no live database).\n";
