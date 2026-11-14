<?php
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\{PropertyDefinition,FieldLabels};
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator;
final class FieldLabelsTest extends TestCase {
 private function definition(array $labels,string $type='text'){return PropertyDefinition::fromJson(['type'=>$type,'labels'=>$labels],TestSources::newPropertyTypeRegistry());}
 public function testKnownTypePreservesLabels():void{$p=$this->definition(['de'=>' Geburtsdatum ','en'=>'Date of birth']);$this->assertSame(['de'=>'Geburtsdatum','en'=>'Date of birth'],$p->getLabels());$this->assertSame($p->getLabels(),$p->toJson()['labels']);}
 public function testUnknownTypePreservesLabels():void{$p=$this->definition(['de'=>'Benennung'],'custom-missing-type');$this->assertSame('Benennung',$p->toJson()['labels']['de']);}
 public function testNoLabelKeepsOldWireShape():void{$p=$this->definition([]);$this->assertArrayNotHasKey('labels',$p->toJson());}
 public function testLanguageOrderAndTechnicalFallback():void{$l=['de'=>'Datum','en'=>'Date'];$this->assertSame('Date',FieldLabels::display($l,'F4',['en','de']));$this->assertSame('Datum',FieldLabels::display($l,'F4',['fr','de']));$this->assertSame('F4',FieldLabels::display($l,'F4',['fr']));}
 public function testLabelsAreNotConstraintSeverityObjects():void{$p=$this->definition(['severity'=>'error','de'=>'Datum']);$this->assertSame('error',$p->getLabels()['severity']);$this->assertSame('Datum',$p->toJson()['labels']['de']);}
 public function testSaveValidationAcceptsLabelsAndRejectsInvalidShapes():void{$v=SchemaContentValidator::newInstance();foreach([['de'=>'Datum'],['en'=>'<script>text</script>']]as$l)$this->assertTrue($v->validate(json_encode(['propertyDefinitions'=>['F4'=>['type'=>'text','labels'=>(object)$l]]])));foreach([['bad_code'=>'X'],['DE'=>'X'],['de'=>42],['de'=>str_repeat('x',4097)]]as$l)$this->assertFalse($v->validate(json_encode(['propertyDefinitions'=>['F4'=>['type'=>'text','labels'=>$l]]])));}
}
