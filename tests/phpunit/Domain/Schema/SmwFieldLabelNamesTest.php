<?php
declare(strict_types=1);
namespace AEWI\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Semantic\{FieldAnnotationName,AnnotationPlanner,FieldNoteAnnotations};
final class SmwFieldLabelNamesTest extends TestCase {
 private function result(string $id='S1',array $labels=['de'=>'Geburtsort','en'=>'Place of birth']):array {
  $field=['name'=>'F12','labels'=>$labels,'type'=>'text','values'=>[['value'=>'Sterzing','origin'=>'own','metadata'=>[]]],'hasStoredValues'=>true,'fieldNotes'=>['type'=>'text','annotate'=>true]];
  return ['annotationLanguages'=>['de','en'],'schema'=>['id'=>$id,'name'=>$id,'properties'=>[$field]],'subject'=>['id'=>'E3','fieldNotes'=>['F12'=>'Anmerkung']],'fields'=>[$field]];
 }
 public function testContentLanguageLabelAndNotSchemaIdOrViewerLanguageIsUsed():void {
  $GLOBALS['wgLanguageCode']='en';
  try {self::assertSame('Geburtsort',FieldAnnotationName::resolve($this->result(),'F12',['properties'=>[]]));}
  finally {unset($GLOBALS['wgLanguageCode']);}
 }
 public function testTwoSchemasCanPublishTheSameSemanticProperty():void {
  foreach(['S1','S2'] as $id)self::assertSame(['Geburtsort=Sterzing'],(new AnnotationPlanner(['properties'=>[]]))->build($this->result($id))->rows);
 }
 public function testRenamingLabelChangesSmwNameWithoutChangingFieldKey():void {
  $r=$this->result('S1',['de'=>'Geboren in']);self::assertSame(['Geboren in=Sterzing'],(new AnnotationPlanner(['properties'=>[]]))->build($r)->rows);self::assertSame('F12',$r['fields'][0]['name']);
 }
 public function testFallbackUsesTechnicalKeyWithoutPrefix():void {self::assertSame('F12',FieldAnnotationName::resolve($this->result('S1',[]),'F12',['properties'=>[]]));}
 public function testFallbackLanguageAndExplicitOverride():void {
  self::assertSame('Place of birth',FieldAnnotationName::resolve($this->result('S1',['en'=>'Place of birth']),'F12',['properties'=>[]]));
  self::assertSame('Anderer Name',FieldAnnotationName::resolve($this->result(),'F12',['properties'=>['S1'=>['F12'=>'Anderer Name']]]));
 }
 public function testInvalidLabelIsRejectedInsteadOfSilentlyEncoded():void {
  $this->expectException(\InvalidArgumentException::class);FieldAnnotationName::resolve($this->result('S1',['de'=>'Ort=Name']),'F12',['properties'=>[]]);
 }
}
