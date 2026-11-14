<?php
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,SchemaReference,PropertyDefinitions,PropertyDefinition};
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId,LocalizedIdentity};
use ProfessionalWiki\SWS\Domain\Rdf\{RdfNamespaces,RdfValueMapperRegistry};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList,SubjectMap};
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\{StringValue,NumberValue,RelationValue};
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Application\Rdf\{RdfPageProjector,SubjectIriResolver};
use ProfessionalWiki\SWS\Application\Semantic\{FieldSnapshot,AnnotationPlanner,FieldNoteAnnotations};
use ProfessionalWiki\SWS\Tests\Data\{TestSources,TestProperty,TestPage,TestRelation};
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use Psr\Log\NullLogger;
function wfMessage($key,...$args){return new class{function inContentLanguage(){return $this;}function text(){return ' (AM)';}};}
$checks=0;function fcheck($ok,$why){global $checks;$checks++;if(!$ok)throw new RuntimeException($why);}
$registry=TestSources::newPropertyTypeRegistry();
$defs=['F4'=>PropertyDefinition::fromJson(['type'=>'text','fieldNotes'=>['type'=>'text','annotate'=>true]],$registry),'F5'=>TestProperty::buildRelation(targetSchemas:[new SchemaName('S12')]),'Name'=>TestProperty::buildText()];
function fschema($id,$defs,$label='Gleicher Titel'){return new Schema(new SchemaName($id),'',new PropertyDefinitions($defs),false,new SchemaId($id),new LocalizedIdentity('de',['de'=>$label]));}
$a=fschema('S11',$defs);$b=fschema('S12',$defs);
$one=new Subject(new SubjectId('E31'),null,$a->getReference(),new StatementList([new Statement(new PropertyName('F4'),'text',new StringValue('Wert A')),new Statement(new PropertyName('Name'),'text',new StringValue('Benannt'))]),['F4'=>'Notiz A'],null,new LocalizedIdentity('de',['de'=>'Entität A']));
$two=new Subject(new SubjectId('E32'),null,$b->getReference(),new StatementList([new Statement(new PropertyName('F4'),'text',new StringValue('Wert B'))]),['F4'=>'Notiz B'],null,new LocalizedIdentity('de',['de'=>'Entität B']));
$relation=TestRelation::build(targetId:'E32');
$one=$one->withStatements($one->getStatements()->withStatement(new Statement(new PropertyName('F5'),'relation',new RelationValue($relation))));
$lookup=new InMemorySchemaLookup($a,$b);$ns=new RdfNamespaces('https://example.org');
$projector=new RdfPageProjector(RdfValueMapperRegistry::withCoreMappers(),$ns,TestSources::newSchemaResolver($lookup),new SubjectIriResolver($ns,TestSources::newRegistry(),new NullLogger()),new NullLogger());
$quads=$projector->projectPage(TestPage::build(id:500,mainSubject:$one,otherSubjects:new SubjectMap($two)))->asArray();
$pred=[];foreach($quads as$q)$pred[$q->subject->value][]=$q->predicate->value;
fcheck(in_array('https://example.org/schema-id/S11/field/F4',$pred['https://example.org/entity/E31']),'Native export scopes first schema');
fcheck(in_array('https://example.org/schema-id/S12/field/F4',$pred['https://example.org/entity/E32']),'Native export scopes second schema');
fcheck(!in_array('https://example.org/schema-id/S12/field/F4',$pred['https://example.org/entity/E31']),'No cross-schema conflation');
fcheck(in_array('https://example.org/schema-id/S11/field/F5',$pred['https://example.org/entity/E31']),'Relation predicate is scoped too');
fcheck(in_array('https://example.org/schema-id/S11/field/Name',$pred['https://example.org/entity/E31']),'Named fields are schema-qualified too');
$ra=FieldSnapshot::reader($a,$one,fn()=>[])->collectForAnnotations();$rb=FieldSnapshot::reader($b,$two,fn()=>[])->collectForAnnotations();
$plan=new AnnotationPlanner(['properties'=>[]]);
fcheck(in_array('S11:F4=Wert A',$plan->build($ra)->rows),'SMW default scopes first schema');
fcheck(in_array('S12:F4=Wert B',$plan->build($rb)->rows),'SMW default scopes second schema');
fcheck((new FieldNoteAnnotations())->build($ra,['properties'=>[]])===['S11:F4 (AM)=Notiz A'],'Note uses same scoped SMW name');
$mapped=['properties'=>['S11'=>['F4'=>'Geburtsdatum']]];
fcheck(in_array('Geburtsdatum=Wert A',(new AnnotationPlanner($mapped))->build($ra)->rows),'Explicit semantic mapping remains available');
fcheck((new FieldNoteAnnotations())->build($ra,$mapped)===['Geburtsdatum (AM)=Notiz A'],'Explicit mapping also controls note name');
fcheck($a->getFieldReference('F4')->equals(fschema('S11',$defs,'Neuer Titel')->getFieldReference('F4')),'Labels cannot rename keys');
fcheck(!$a->getFieldReference('F4')->equals($b->getFieldReference('F4')),'Equal labels and numbers do not merge identities');
fcheck(RdfNamespaces::fieldPropertyPath('F4',SchemaReference::identified(new SchemaId('S11'),'remote'))==='/schema-source-id/remote/S11/field/F4','Foreign schema has a distinct scope');
$presentation=\SWSBaseBridge\GraphFieldPresentation::fromSchema($a,'https://example.org/wiki/SWSGRAPHTITLETOKEN',$one);
fcheck($presentation['definitions'][0]['predicatePath']==='/schema-id/S11/field/F4','Graph receives same exact predicate path');
// Effective RDF exports follow the same rule, not only the native projection.
$view=['version'=>1,'readOnly'=>true,'complete'=>true,'scope'=>'current','page'=>['id'=>500,'title'=>'Test','url'=>'https://example.org/wiki/Test'], 'subjects'=>[
 ['id'=>'E31','schema'=>['id'=>'S11'],'displayName'=>'A','fields'=>[['name'=>'F4','type'=>'text','effectiveValues'=>[['kind'=>'text','value'=>'Wert A']],'effectiveOrigin'=>'own','ownValues'=>[['kind'=>'text','value'=>'Wert A']],'external'=>null]]]
]];
$export=(new \SWSBaseBridge\DataViewRdfProjector($ns))->project($view);
$exportPredicates=array_map(static fn($quad)=>$quad->predicate->value,$export->asArray());
fcheck(in_array('https://example.org/schema-id/S11/field/F4',$exportPredicates),'Effective data-view RDF uses same scope');
fcheck(!in_array('https://example.org/prop/F4',$exportPredicates),'No legacy global numbered predicate in effective export');
echo "$checks D5 schema/field/native-RDF/relations/SMW/notes/Graph checks passed.\n";
