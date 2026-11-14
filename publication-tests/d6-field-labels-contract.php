<?php
require __DIR__.'/d5-field-scope-contract.php';
use ProfessionalWiki\SWS\Domain\Schema\{PropertyDefinition,FieldLabels};
use ProfessionalWiki\SWS\Application\Semantic\{FieldSnapshot,AnnotationPlanner};
$labels=['de'=>'Geburtsdatum','en'=>'Date of birth'];
$translated=$defs;
$translated['F4']=PropertyDefinition::fromJson(['type'=>'text','labels'=>$labels,'fieldNotes'=>['type'=>'text','annotate'=>true]],$registry);
$translated['F5']=PropertyDefinition::fromJson(['type'=>'relation','labels'=>['en'=>'Related entity'],'targetSchemas'=>['S12']],$registry);
$translatedSchema=fschema('S11',$translated);
fcheck($translatedSchema->getFieldReference('F4')->equals($a->getFieldReference('F4')),'Translation preserves schema/key identity');
fcheck($translated['F4']->getLabels()===$labels,'Labels survive definition reading');
fcheck(FieldLabels::display($labels,'F4',['fr','de'])==='Geburtsdatum','Explicit fallback order');
fcheck($translatedSchema->getFieldReference('Date of birth')===null,'Display label cannot select a field');
$record=FieldSnapshot::reader($translatedSchema,$one,fn()=>[])->collectForAnnotations();
fcheck((new AnnotationPlanner(['properties'=>[]]))->build($record)->rows===$plan->build($ra)->rows,'Translation preserves SMW targets and values');
$graph=\SWSBaseBridge\GraphFieldPresentation::fromSchema($translatedSchema,'https://example.org/wiki/SWSGRAPHTITLETOKEN',$one,'en');
fcheck($graph['definitions'][0]['name']==='F4' && $graph['definitions'][0]['displayLabel']==='Date of birth','Graph separates label and technical key');
fcheck($graph['definitions'][0]['predicatePath']==='/schema-id/S11/field/F4','Graph predicate stays unchanged');
$lookup->updateSchema($translatedSchema);
$labelQuads=$projector->projectPage(\ProfessionalWiki\SWS\Tests\Data\TestPage::build(id:500,mainSubject:$one))->asArray();
$found=[];foreach($labelQuads as $q){if($q->predicate->value==='http://www.w3.org/2000/01/rdf-schema#label')$found[]=$q->subject->value;}
fcheck(in_array('https://example.org/schema-id/S11/field/F4',$found),'Native RDF labels describe the stable field predicate');
$view['subjects'][0]['fields'][0]['labels']=$labels;
$effective=(new \SWSBaseBridge\DataViewRdfProjector($ns))->project($view)->asArray();
fcheck(count($effective)>count($export->asArray()),'Full data-view RDF includes language labels');
echo "$checks D6 multilingual field checks passed.\n";
