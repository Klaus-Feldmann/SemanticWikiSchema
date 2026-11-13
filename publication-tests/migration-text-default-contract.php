<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyDefinitions,PropertyDefinition,PropertyName};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList};
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Application\Semantic\{FieldSnapshot,AnnotationPlanner};
use ProfessionalWiki\SWS\Tests\Data\TestSources;

$nDefault=0;function defaultCheck(bool $ok,string $why):void{global $nDefault;$nDefault++;if(!$ok)throw new RuntimeException($why);}
$registry=TestSources::newPropertyTypeRegistry();
$property=PropertyDefinition::fromJson(['type'=>'text','default'=>['SWS Migration Feld ergänzt']],$registry);
$schema=new Schema(new SchemaName('S2'),'',new PropertyDefinitions(['Neu'=>$property]),false);
$subject=new Subject(new SubjectId('E1'),null,SchemaReference::local(new SchemaName('S2')),new StatementList());
$before=serialize($subject);$noFetch=static fn()=>throw new RuntimeException('Unexpected source fetch');
$reader=FieldSnapshot::reader($schema,$subject,$noFetch);$result=$reader->collect('Neu');
defaultCheck($result['fields'][0]['values'][0]['value']==='SWS Migration Feld ergänzt'&&$result['fields'][0]['values'][0]['origin']==='schema','Schema placeholder is an effective readable value');
defaultCheck(!$result['fields'][0]['hasStoredValues']&&serialize($subject)===$before,'Default does not create author statements');
defaultCheck($reader->collect('Neu','own')['fields'][0]['values']===[],'Own-only read excludes schema default');
defaultCheck($reader->collect('Neu','external')['fields'][0]['values']===[],'External-only read excludes schema default');
$plan=(new AnnotationPlanner(['properties'=>[]]))->build($reader->collectForAnnotations());defaultCheck($plan->rows===['Neu=SWS Migration Feld ergänzt'],'Default is available to SMW lists');
$own=$subject->withStatements(new StatementList([new ProfessionalWiki\SWS\Domain\Statement(new PropertyName('Neu'),'text',new ProfessionalWiki\SWS\Domain\Value\StringValue('Forschungswert'))]));
defaultCheck(FieldSnapshot::reader($schema,$own,$noFetch)->collect('Neu')['fields'][0]['values'][0]['value']==='Forschungswert','Author value overrides placeholder');
$view=SWSBaseBridge\DataViewAssembler::subject($subject,$schema,[],static fn()=>null,'store');
defaultCheck($view['fields'][0]['effectiveOrigin']==='schema'&&$view['fields'][0]['ownValues']===[]&&$view['fields'][0]['effectiveValues'][0]['value']==='SWS Migration Feld ergänzt','Complete Data view shares default without claiming author data');
echo "$nDefault schema text default/read/SMW/Data-view/author-override checks passed.\n";
