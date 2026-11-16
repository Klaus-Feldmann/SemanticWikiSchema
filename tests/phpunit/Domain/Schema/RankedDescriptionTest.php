<?php
declare(strict_types=1);
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\EntryPoints\Processing\EffectivePageDescription as Ranked;
final class RankedDescriptionTest extends TestCase {
 private function item(string $value,string $status='resolved'):array{return ['status'=>$status,'description'=>['value'=>$value,'language'=>'de']];}
 public function testShortdescWinsWithoutAnySourceLookup():void {
  self::assertSame(['text'=>'Eigen','source'=>'shortdesc'],Ranked::choose(' Eigen ',static fn()=>throw new \RuntimeException('Must not fetch')));
 }
 public function testLocalWinsAndWikidataIsFallback():void {
  $sources=['wikidata'=>$this->item('Extern'),'wikibase-local'=>$this->item('Lokal')];
  self::assertSame('Lokal',Ranked::choose('',fn()=>$sources)['text']);
  $sources['wikibase-local']=$this->item('  ');self::assertSame('Extern',Ranked::choose(null,fn()=>$sources)['text']);
 }
 public function testUnavailableLocalIsNotUsedEvenIfItHasText():void {
  self::assertSame('Extern',Ranked::choose(null,fn()=>['wikibase-local'=>$this->item('Privat','unavailable'),'wikidata'=>$this->item('Extern')])['text']);
 }
 public function testMissingSourcesDoNotProducePlaceholder():void {self::assertNull(Ranked::choose(null,fn()=>[]));}
 public function testTextIsNotExpandedAndZeroIsNotEmpty():void {
  self::assertSame('{{Vorlage}} [[Seite]] | =',Ranked::choose('{{Vorlage}} [[Seite]] | =',fn()=>[])['text']);
  self::assertSame('0',Ranked::choose('0',fn()=>[])['text']);
 }
 public function testRemovalFallsBackToNextRankOnFreshProcessing():void {
  $source=['wikidata'=>$this->item('Extern'),'wikibase-local'=>$this->item('Lokal')];
  self::assertSame('Eigen',Ranked::choose('Eigen',fn()=>$source)['text']);
  self::assertSame('Lokal',Ranked::choose(null,fn()=>$source)['text']);unset($source['wikibase-local']);
  self::assertSame('Extern',Ranked::choose(null,fn()=>$source)['text']);
 }
}
