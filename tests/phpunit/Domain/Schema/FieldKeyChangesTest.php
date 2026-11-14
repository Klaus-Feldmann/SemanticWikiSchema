<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\{ FieldKeyChanges, SchemaReference, DuplicateFieldNumbers };
use ProfessionalWiki\SWS\Domain\Rdf\RdfNamespaces;
use ProfessionalWiki\SWS\Domain\Identity\SchemaId;
use ProfessionalWiki\SWS\Application\Semantic\FieldAnnotationName;

final class FieldKeyChangesTest extends TestCase {
 public function testTransfersValuesNotesAndEverySnapshotLanguageWithoutRewritingText(): void {
  $entity=['schema'=>['id'=>'S1'],'statements'=>['Ort'=>['type'=>'text','value'=>['Ort bleibt Text']],'Andere'=>['type'=>'relation','value'=>['id'=>'R1','target'=>'E3']]],'fieldNotes'=>['Ort'=>'[[Ort]] bleibt Text'], 'sourceSnapshot'=>['languages'=>[]]];
  foreach(['de','en']as$lang) {
   $entry=['lastAttempt'=>['name'=>'Ort','propertyId'=>'P19','values'=>[['value'=>'Ort bleibt Text']]], 'lastSuccess'=>['at'=>'2026-11-13','result'=>['name'=>'Ort','values'=>[['id'=>'Q1']]]]];
   $entity['sourceSnapshot']['languages'][$lang]=['fields'=>['Ort'=>$entry], 'identities'=>['wikidata'=>['id'=>'Q3']]];
  }
  $next=FieldKeyChanges::transfer($entity,[['Ort'=>'N1']]);
  self::assertSame($entity['statements']['Ort'],$next['statements']['N1']);
  self::assertSame('[[Ort]] bleibt Text',$next['fieldNotes']['N1']);
  self::assertSame($entity['statements']['Andere'],$next['statements']['Andere']);
  foreach(['de','en']as$lang){self::assertSame('N1',$next['sourceSnapshot']['languages'][$lang]['fields']['N1']['lastAttempt']['name']);self::assertSame('N1',$next['sourceSnapshot']['languages'][$lang]['fields']['N1']['lastSuccess']['result']['name']);}
  self::assertSame($next,FieldKeyChanges::transfer($next,[['Ort'=>'N1']]));
 }
 public function testDelayedEntityAppliesSeveralChangesOnlyOnceAndAllowsLaterKeyReuse():void {
  $original=['statements'=>['Alt'=>1],'fieldNotes'=>[]];
  $history=[['Alt'=>'N1'],['N1'=>'Z1'],['Z1'=>'Alt']];
  self::assertSame(['statements'=>['Alt'=>1],'fieldNotes'=>[],'fieldKeyVersion'=>3],FieldKeyChanges::transfer($original,$history));
  $current=['statements'=>['N1'=>2],'fieldKeyVersion'=>3];self::assertSame($current,FieldKeyChanges::transfer($current,$history));
 }
 public function testSimultaneousSwapsAreNotSequentialOverwrites():void {
  self::assertSame(['B'=>1,'A'=>2],FieldKeyChanges::transfer(['statements'=>['A'=>1,'B'=>2]],[['A'=>'B','B'=>'A']])['statements']);
 }
 public function testCollisionIsRefusedWithoutChangingOriginal():void {
  $entity=['statements'=>['A'=>1,'B'=>2]];
  try{FieldKeyChanges::transfer($entity,[['A'=>'B']]);self::fail('Overwrite admitted');}catch(\InvalidArgumentException){self::assertSame(['statements'=>['A'=>1,'B'=>2]],$entity);}
 }
 public function testFutureEntityVersionIsRefused():void { $this->expectException(\InvalidArgumentException::class);FieldKeyChanges::transfer(['fieldKeyVersion'=>2],[]); }
 public function testHistoryCannotBeRemovedOrRewritten():void {
  $this->expectException(\InvalidArgumentException::class);FieldKeyChanges::validateEdit(['fieldKeyChanges'=>[['A'=>'B']]],[]);
 }
 public function testOnlyCompatibleExplicitTransfersAreAccepted():void {
  FieldKeyChanges::validateEdit(['propertyDefinitions'=>['A'=>['type'=>'text']]],['propertyDefinitions'=>['Z1'=>['type'=>'text']],'fieldKeyChanges'=>[['A'=>'Z1']]]);
  $this->expectException(\InvalidArgumentException::class);FieldKeyChanges::validateEdit(['propertyDefinitions'=>['A'=>['type'=>'text']]],['propertyDefinitions'=>['Z1'=>['type'=>'number']],'fieldKeyChanges'=>[['A'=>'Z1']]]);
 }
 public function testAllSpellingsHaveSchemaScopedRdfAndSmwDefaults():void {
  $one=SchemaReference::identified(new SchemaId('S1'));$two=SchemaReference::identified(new SchemaId('S2'));
  self::assertNotSame(RdfNamespaces::fieldPropertyPath('Has author',$one),RdfNamespaces::fieldPropertyPath('Has_author',$one));
  self::assertSame('S1:a%3Db',FieldAnnotationName::resolve(['schema'=>['name'=>'S1']],'a=b',['properties'=>[]]));
  self::assertSame('S1:a%253Db',FieldAnnotationName::resolve(['schema'=>['name'=>'S1']],'a%3Db',['properties'=>[]]));
  foreach(['F1','N1','Z1','Klassenzuordnung','Ort:Name']as$key){self::assertNotSame(RdfNamespaces::fieldPropertyPath($key,$one),RdfNamespaces::fieldPropertyPath($key,$two));self::assertSame('S1:'.$key,FieldAnnotationName::resolve(['schema'=>['name'=>'S1','id'=>'S1']],$key,['properties'=>[]]));}
 }
 public function testExplicitSmwMappingSurvivesKeyChanges():void {
  $result=['schema'=>['name'=>'S1','fieldKeyChanges'=>[['A'=>'N1'],['N1'=>'Z1']]]];
  self::assertSame('Fachbegriff',FieldAnnotationName::resolve($result,'Z1',['properties'=>['S1'=>['A'=>'Fachbegriff']]]));
  self::assertSame('Neu',FieldAnnotationName::resolve($result,'Z1',['properties'=>['S1'=>['A'=>'Alt','Z1'=>'Neu']]]));
 }
 public function testUnchangedSourceBindingNeedsNoLookupWhenOnlyItsKeyChanges():void {
  $definition=['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P19']];
  \SWSBaseBridge\SchemaPropertyBindings::validate(['propertyDefinitions'=>['N1'=>$definition],'fieldKeyChanges'=>[['Ort'=>'N1']]],['propertyDefinitions'=>['Ort'=>$definition]],null,static fn()=>throw new \RuntimeException('No lookup needed'));
  self::assertTrue(true);
 }

 public function testDuplicateKeysAreRejectedForEverySpelling():void {
  foreach(['F1','N1','Z1','Klassenzuordnung']as$key){self::assertSame([$key],DuplicateFieldNumbers::find('{"propertyDefinitions":{"'.$key.'":{"type":"text"},"'.$key.'":{"type":"text"}}}'));}
 }
 public function testReorderingEntriesInAnExistingSimultaneousMapDoesNotRewriteHistory():void {
  FieldKeyChanges::validateEdit(['fieldKeyChanges'=>[['A'=>'N1','B'=>'Z1']]],['fieldKeyChanges'=>[['B'=>'Z1','A'=>'N1']]]);
  self::assertTrue(true);
 }

}
