<?php
use PHPUnit\Framework\TestCase;
use SWSBaseBridge\DescriptionPresentation;
final class DescriptionPresentationTest extends TestCase {
 private function item(string $text,string $language='de'):array {return ['status'=>'resolved','id'=>'Q5','url'=>'https://example.org/Q5','description'=>['language'=>$language,'value'=>$text]];}
 public function testDifferentDescriptionsPreserveSourceOrder():void {
  $v=DescriptionPresentation::groups(['wikidata'=>$this->item('Kardinal'),'wikibase-local'=>$this->item('Universalgelehrter')]);
  self::assertCount(2,$v);self::assertSame('Kardinal',$v[0]['text']);self::assertSame('wikibase-local',$v[1]['sources'][0]['source']);
 }
 public function testSameTextAndLanguageAreGrouped():void {
  $v=DescriptionPresentation::groups(['wikidata'=>$this->item(' Mensch '),'wikibase-local'=>$this->item('Mensch')]);
  self::assertCount(1,$v);self::assertCount(2,$v[0]['sources']);
 }
 public function testDifferentLanguagesRemainSeparate():void {
  self::assertCount(2,DescriptionPresentation::groups(['wikidata'=>$this->item('Astronom','de'),'wikibase-local'=>$this->item('Astronom','en')]));
 }
 public function testMissingAndFailedDescriptionsProduceNoPlaceholder():void {
  self::assertSame([],DescriptionPresentation::groups(['wikidata'=>['status'=>'missing'],'wikibase-local'=>$this->item('  ')]));
  $item=$this->item('Text');$item['status']='unavailable';self::assertSame([],DescriptionPresentation::groups(['wikidata'=>$item]));
 }
 public function testTextRemainsTextWithoutMarkupExecution():void {
  $v=DescriptionPresentation::groups(['wikidata'=>$this->item('<script>bad()</script>')]);self::assertSame('<script>bad()</script>',$v[0]['text']);
 }

 public function testDisplayPrefersPageThenLocalThenExternalWithoutChangingSourceGroups():void {
  $sources=['wikidata'=>$this->item('Wikidata'),'wikibase-local'=>$this->item('Wikibase')];
  $page=['status'=>'resolved','text'=>'Eigene Seite','language'=>'de','pageId'=>42,'url'=>'https://example.org/wiki/Test'];
  self::assertSame('Eigene Seite',DescriptionPresentation::select($sources,$page)['text']);
  self::assertSame('page',DescriptionPresentation::select($sources,$page)['sources'][0]['source']);
  self::assertSame('Wikibase',DescriptionPresentation::select($sources,['status'=>'missing'])['text']);
  unset($sources['wikibase-local']);self::assertSame('Wikidata',DescriptionPresentation::select($sources,[])['text']);
  self::assertNull(DescriptionPresentation::select([],[]));
 }
 public function testEmptyLocalDescriptionFallsBackAndAllDescriptionsRemainAvailable():void {
  $sources=['wikidata'=>$this->item('Wikidata'),'wikibase-local'=>$this->item('  ')];
  self::assertSame('Wikidata',DescriptionPresentation::select($sources,[])['text']);
  $sources['wikibase-local']=$this->item('Lokal');self::assertCount(2,DescriptionPresentation::groups($sources));
  self::assertSame('Lokal',DescriptionPresentation::select($sources,[])['text']);
 }
}
