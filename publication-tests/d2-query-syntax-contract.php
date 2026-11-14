<?php
// Reuse the controlled host and actual store/cache/permissions/SMW reader from D4.
require __DIR__.'/direct-field-read-contract.php';
use CollectorHarness\State as Q;
use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList};
use ProfessionalWiki\SWS\Domain\Schema\{PropertyName,PropertyDefinition,PropertyDefinitions,Schema,SchemaName};
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Source\SourceSnapshot;
use ProfessionalWiki\SWS\EntryPoints\{SWSValueParserFunction,ParserFieldLookup};
use ProfessionalWiki\SWS\SWSExtension;
$checks=0;
function qcheck($ok,$why){global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
Q::$mode='store';Q::$denied=[];Q::$requests=[];Q::$cache=[];
$props=$localProperties;
$props['Ort']=PropertyDefinition::fromJson(['type'=>'text','externalProperty'=>['source'=>'wikibase-local','id'=>'P19'],'fieldNotes'=>['type'=>'text','annotate'=>true]],$registry);
$props['F12']=PropertyDefinition::fromJson(['type'=>'text'],$registry);
SWSExtension::$schema=new Schema(new SchemaName('Person'),'',new PropertyDefinitions(array_intersect_key($props,array_flip(['Ort','F12','Name']))),false);
$eid=new SubjectId('E3');
$source=SourceSnapshot::recordAttempt($eid,943,201,[901=>100],'de','2026-11-13T12:00:00Z',[storeField('Ort','wikibase-local','Q500')],[],null);
$entity=new Subject($eid,null,$subject->getSchemaReference(),new StatementList([
 new Statement(new PropertyName('F12'),'text',new StringValue('Eins','Zwei')),
 new Statement(new PropertyName('Name'),'text',new StringValue('<b>Entität</b>'))
]),['Ort'=>'Notiz E3'],$source,new \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity('de'));
setStorePage($entity,202);
$ownResolver=new class($entity) extends \ProfessionalWiki\SWS\Application\SubjectResolver {
 public int $idReads=0,$mainReads=0;
 public function __construct(private Subject $entity){}
 public function resolveById(string $id): ?Subject{$this->idReads++;return $id==='E3'?$this->entity:null;}
 public function resolveMainByTitle(Title $title): ?Subject{$this->mainReads++;return $this->entity;}
 public function resolveDisplayValue(Subject $s,Statement $v): \ProfessionalWiki\SWS\Domain\Value\NeoValue{return $v->getValue();}
};
$p=new Parser();$fn=new SWSValueParserFunction($ownResolver,ParserFieldLookup::create($p));
qcheck($fn->handle($p,'E3','F12')[0]==='Eins, Zwei','Explicit E-ID and schema-local field');
qcheck($fn->handle($p,' F12 ')[0]==='Eins, Zwei','One argument reads main entity');
qcheck($fn->handle($p,' E3 ',' F12 ','separator=;')[0]===wfEscapeWikiText('Eins;Zwei'),'Whitespace and separator');
qcheck($fn->handle($p,'E3','Name')[0]===wfEscapeWikiText('<b>Entität</b>'),'Ordinary field name and escaping');
qcheck($fn->handle($p,'E3','Ort')[0]==='Gespeicherter Ort','Explicit ID uses local source stock');
qcheck($fn->handle($p,'E3','Ort','part=authorNote')[0]==='Notiz E3','Explicit ID field note');
qcheck($fn->handle($p,'E3','F12','origin=own')[0]==='Eins, Zwei','Own-only resolver receives E-ID');
qcheck($ownResolver->idReads===1&&$ownResolver->mainReads===0,'Explicit own call never falls back to main entity');
qcheck($fn->handle($p,'E999','F12')==='','Missing E-ID does not fall back');
foreach([
 ['F12','subject=E3'],['F12','entity=E3'],['E3','F12','subject=E4'],
 ['E3','F12','page=Artikel'],['F12','E3'],['E0','F12'],['E03','F12'],
 ['E-3','F12'],['E9999999999999999999','F12'],['E3',''],['E3','F12','Extra'],
 ['E3','F12','origin=invalid'],['E3','F12','separator=;','separator=,'],
 ['F12','page='],['E3','F12','unknown=yes'],['E3','F12','entity=E3']
] as $args){
 $before=$p->expensive;$ids=$ownResolver->idReads;$main=$ownResolver->mainReads;
 qcheck($fn->handle($p,...$args)===''&&$p->expensive===$before&&$ids===$ownResolver->idReads&&$main===$ownResolver->mainReads,'Reject before lookup: '.implode('|',$args));
}
Q::$denied=['Artikel'];$denied=new SWSValueParserFunction($ownResolver,ParserFieldLookup::create(new Parser()));
qcheck($denied->handle(new Parser(),'E3','F12')==='','Actual effective lookup denies unreadable host');Q::$denied=[];
qcheck(Q::$requests===[],'Store ID queries do not request external sources');
// Cache mode uses the same canonical target and keeps the configured source resolution.
Q::$mode='cache';Q::$cache=[];Q::$requests=[];
$c=new Parser();$cacheFn=new SWSValueParserFunction($ownResolver,ParserFieldLookup::create($c));
$cacheValue=$cacheFn->handle($c,'E3','Ort');
qcheck(($cacheValue[0]??null)==='Cache-Abfrage'&&count(Q::$requests)>0,'Cache mode ID query retains configured source behavior');
$before=count(Q::$requests);
qcheck($cacheFn->handle($c,'E3','F12','origin=own')[0]==='Eins, Zwei'&&count(Q::$requests)===$before,'Own-only does not add source requests');
$limited=new class extends Parser { public function incrementExpensiveFunctionCount(): bool{return false;} };
qcheck($fn->handle($limited,'E3','F12')==='','Parser expensive-function limit respected');
Q::$mode='store';
echo "$checks D2 positional-query/permission/store/cache checks passed (controlled MediaWiki host).\n";
