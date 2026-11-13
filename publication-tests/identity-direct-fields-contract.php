<?php
declare( strict_types = 1 );
require __DIR__ . '/direct-field-read-contract.php';
use CollectorHarness\State;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Subject\{Subject, SubjectId, SubjectLabel, StatementList};
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId, LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Schema\{Schema, SchemaName, SchemaReference, PropertyDefinitions, PropertyDefinition, PropertyName};
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\EntryPoints\EffectiveFieldLookup;
use MediaWiki\Context\TestContext;
use MediaWiki\Title\Title;
$count=0;
function d4field(bool $ok,string $why):void{global$count;if(!$ok)throw new RuntimeException($why);$count++;}
$terms=new LocalizedIdentity('de',['de'=>'Gödel','en'=>'Goedel'],['de'=>'Forscher','en'=>'Researcher']);
$own=new Subject(new SubjectId('E7'),new SubjectLabel('Gödel'),SchemaReference::identified(new SchemaId('S9')),
 new StatementList([new Statement(new PropertyName('Notiz'),'text',new StringValue('Eigener Wert'))]),identity:$terms);
SWSExtension::$schema=new Schema(new SchemaName('S9'),'',new PropertyDefinitions(['Notiz'=>PropertyDefinition::fromJson(['type'=>'text'],$registry)]),false,new SchemaId('S9'),new LocalizedIdentity('de',['de'=>'Person'],[]));
State::$mode='store';State::$publish=true;State::$publishedId=null;State::$denied=[];setStorePage($own,500);
$requests=count(State::$requests);$lookups=State::$identityLookups;
$resolve=static fn(SubjectId $id):SubjectId=>$id->text==='s11111111111111'?new SubjectId('E7'):$id;
$reader=new EffectiveFieldLookup(new TestContext(),canonicalId:$resolve);
$byId=$reader->read(new Title(),['subject'=>'E7','field'=>'Notiz','language'=>'en']);
$byOld=$reader->read(new Title(),['subject'=>'s11111111111111','field'=>'Notiz','language'=>'en']);
d4field(json_encode($byId)===json_encode($byOld),'Old and new entry IDs yield identical canonical field results');
d4field($byId['subject']['id']==='E7'&&$byId['subject']['label']==='Goedel','Requested display language uses own entity terms');
d4field((array)$byId['subject']['labels']===$terms->labels,'Full language maps survive direct field lookup');
d4field($byId['schemaIdentity']['id']==='S9','Schema identity accompanies direct read');
d4field($byId['fields'][0]['values'][0]['value']==='Eigener Wert','Own research value unchanged');
d4field(count(State::$requests)===$requests&&State::$identityLookups===$lookups,'Store direct reads never invoke external sources');
State::$denied=['Artikel'];
d4field((new EffectiveFieldLookup(new TestContext(),canonicalId:$resolve))->read(new Title(),['subject'=>'s11111111111111'])['missing'],'Alias cannot bypass page read authorization');
State::$denied=[];
echo "$count D4 direct-field canonical-ID/language/store/read-permission checks passed (actual field path with controlled MediaWiki host).\n";
