<?php
use PHPUnit\Framework\TestCase;
use SWSBaseBridge\DataViewAssembler;
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,SchemaReference,PropertyDefinitions,PropertyCore,PropertyName};
use ProfessionalWiki\SWS\Domain\Schema\Property\{TextProperty,SelectProperty,SelectOption,WikitextProperty};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList};
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\{StringValue,NumberValue,BooleanValue,UrlValue};
final class DataViewAssemblerTest extends TestCase {
 private function view(array $statements,array $properties=[],array $remote=[]):array {
  $subject=new Subject(new SubjectId('sF1FCQPNcwQdw4B'),null,SchemaReference::local(new SchemaName('Person')),new StatementList($statements));
  return DataViewAssembler::subject($subject,new Schema(new SchemaName('Person'),'Example',new PropertyDefinitions($properties),false),['fields'=>$remote],static fn($id)=>['id'=>$id,'status'=>'unavailable']);
 }
 private function st(string $n,string $t,$v):Statement{return new Statement(new PropertyName($n),$t,$v);}
 public function testOwnPriorityAndSourceIdentity():void {
  $r=['name'=>'Ort','source'=>'wikidata','entityId'=>'Q5879','propertyId'=>'P20','status'=>'resolved','values'=>[['kind'=>'entity','id'=>'Q991','label'=>'Weimar']],'candidates'=>[['rank'=>'normal']]];
  $v=$this->view([$this->st('Ort','text',new StringValue('Eigener Ort'))],[],[$r]);
  self::assertSame('own',$v['fields'][0]['effectiveOrigin']);self::assertSame($r,$v['fields'][0]['external']);self::assertSame('Eigener Ort',$v['fields'][0]['effectiveValues'][0]['value']);
 }
 public function testScalarAndUrlMetadata():void {
  $u=['url'=>'https://example.org/?a=1&b=2','label'=>'Titel','caption'=>'Quelle','captionPage'=>'Vermerk:Quelle'];
  $v=$this->view([$this->st('Zahl','number',new NumberValue(0)),$this->st('Bool','boolean',new BooleanValue(false)),$this->st('Text','text',new StringValue('A','B')),$this->st('URL','url',new UrlValue([$u]))]);
  self::assertSame(0,$v['fields'][0]['effectiveValues'][0]['value']);self::assertSame(false,$v['fields'][1]['effectiveValues'][0]['value']);self::assertCount(2,$v['fields'][2]['ownValues']);self::assertSame($u,$v['fields'][3]['ownValues'][0]['value']);
 }
 public function testSelectionAndOrder():void {
  $p=new SelectProperty(new PropertyCore('',false,null),[new SelectOption('abc','Abhandlung')],false);
  $v=$this->view([$this->st('Art','select',new StringValue('abc'))],['Leer'=>TextProperty::fromPartialJson(new PropertyCore('',false,null),[]),'Art'=>$p]);
  self::assertSame('Leer',$v['fields'][0]['name']);self::assertSame('empty',$v['fields'][0]['status']);self::assertSame('Abhandlung',$v['fields'][1]['ownValues'][0]['label']);self::assertSame('abc',$v['fields'][1]['ownValues'][0]['id']);
 }
 public function testFallbackAndUnavailable():void {
  $p=TextProperty::fromPartialJson(new PropertyCore('',false,null),[]);$r=['name'=>'Ort','status'=>'unavailable','values'=>[]];
  $v=$this->view([],['Ort'=>$p],[$r]);self::assertFalse($v['complete']);self::assertSame('unavailable',$v['fields'][0]['status']);
  $r['status']='resolved';$r['values']=[['kind'=>'entity','id'=>'Q5','label'=>'Mensch']];$v=$this->view([],['Ort'=>$p],[$r]);self::assertTrue($v['complete']);self::assertSame('external',$v['fields'][0]['effectiveOrigin']);
 }
 public function testCalendarsAndOffset():void {
  $v=$this->view([$this->st('Geburt','historicalDate',new StringValue('-0427')),$this->st('Zeit','dateTime',new StringValue('2026-11-03T09:00:00+02:00')),$this->st('Jahr','historicalDate',new StringValue('1582'))]);
  self::assertSame(-427,$v['fields'][0]['ownValues'][0]['year']);self::assertSame('year',$v['fields'][0]['ownValues'][0]['precision']);self::assertSame('julian',$v['fields'][0]['ownValues'][0]['calendar']);self::assertSame('2026-11-03T09:00:00+02:00',$v['fields'][1]['ownValues'][0]['value']);self::assertSame('mixed',$v['fields'][2]['ownValues'][0]['calendar']);
 }
 public function testImagesAndUnknownSelection():void {
  $v=$this->view([$this->st('Bild','image',new StringValue('commons:Portrait.jpg')),$this->st('Art','select',new StringValue('missing'))],['Art'=>new SelectProperty(new PropertyCore('',false,null),[],false)]);
  self::assertSame('unresolved',$v['fields'][0]['status']);self::assertFalse($v['complete']);self::assertSame('commons',$v['fields'][1]['ownValues'][0]['repository']);
 }
 public function testExecutableSchemaCodeExcludedButPassiveWikitextPreserved():void {
  $core=new PropertyCore('',false,null);
  $program=WikitextProperty::fromPartialJson($core,['execute'=>true,'wikitext'=>'{{SWS}}']);
  $passive=WikitextProperty::fromPartialJson($core,[]);
  $v=$this->view([$this->st('Programm','wikitext',new StringValue('dormant')),$this->st('Notiz','wikitext',new StringValue('{{Beleg}}'))],['Programm'=>$program,'Notiz'=>$passive]);
  self::assertCount(1,$v['fields']);self::assertSame('Notiz',$v['fields'][0]['name']);self::assertSame('{{Beleg}}',$v['fields'][0]['ownValues'][0]['value']);
 }
 public function testRelationTargetAndUnresolvedDateRetainRawValue():void {
  $relation=new \ProfessionalWiki\SWS\Domain\Relation\Relation(new \ProfessionalWiki\SWS\Domain\Relation\RelationId('rF1FCQPNcwQdw4B'),new SubjectId('sF16iHXBMTQdHDY'));
  $v=$this->view([$this->st('Bezug','relation',new \ProfessionalWiki\SWS\Domain\Value\RelationValue($relation)),$this->st('Datum','historicalDate',new StringValue('invalid'))]);
  self::assertSame('sF16iHXBMTQdHDY',$v['fields'][0]['ownValues'][0]['target']['id']);self::assertSame('unavailable',$v['fields'][0]['ownValues'][0]['target']['status']);self::assertFalse($v['complete']);self::assertSame('invalid',$v['fields'][1]['ownValues'][0]['value']);
 }
}
