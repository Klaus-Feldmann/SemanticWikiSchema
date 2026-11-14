<?php
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,PropertyDefinitions,DuplicateFieldNumbers};
use ProfessionalWiki\SWS\Domain\Identity\{SchemaId,LocalizedIdentity};
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
final class SchemaFieldReferenceTest extends TestCase {
 private function schema(string $id,string $label='Person'):Schema{return new Schema(new SchemaName($id),'',new PropertyDefinitions(['F4'=>TestProperty::buildText(),'Geburtsdatum'=>TestProperty::buildText()]),false,new SchemaId($id),new LocalizedIdentity('de',['de'=>$label]));}
 public function testSameKeyInDifferentSchemasIsNotSameField():void{$this->assertFalse($this->schema('S1')->getFieldReference('F4')->equals($this->schema('S2')->getFieldReference('F4')));}
 public function testLabelChangesDoNotRenameField():void{$this->assertTrue($this->schema('S1','Person')->getFieldReference('F4')->equals($this->schema('S1','Mensch')->getFieldReference('F4')));}
 public function testExactLookupAndLegacyNames():void{$s=$this->schema('S1');$this->assertNull($s->getFieldReference('f4'));$this->assertNull($s->getFieldReference(' F4 '));$this->assertSame('Geburtsdatum',$s->getFieldReference('Geburtsdatum')->field);$this->assertSame(['schema'=>['id'=>'S1'],'field'=>'F4'],$s->getFieldReference('F4')->toJson());}
 public function testRawDuplicateNumbersCannotDisappearInJsonDecode():void{$this->assertSame(['F4'],DuplicateFieldNumbers::find('{"propertyDefinitions":{"F4":{},"F4":{}}}'));$this->assertSame(['F4'],DuplicateFieldNumbers::find('{"schema":{"propertyDefinitions":{"F4":{},"\\u00464":{}}}}'));}
 public function testSeparateSchemasAndStringsDoNotProduceFalseDuplicates():void{$this->assertSame([],DuplicateFieldNumbers::find('{"a":{"propertyDefinitions":{"F4":{}}},"b":{"propertyDefinitions":{"F4":{}}}}'));$this->assertSame([],DuplicateFieldNumbers::find('{"propertyDefinitions":{"F4":{"description":"\\\"F4\\\": {}","values":[1,true,null]},"F5":{}}}'));$this->assertSame([],DuplicateFieldNumbers::find('invalid JSON'));}
}
