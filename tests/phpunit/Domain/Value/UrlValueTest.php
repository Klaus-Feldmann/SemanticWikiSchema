<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\Value;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Value\UrlValue;
use ProfessionalWiki\SWS\Domain\Value\ValueType;
use InvalidArgumentException;
class UrlValueTest extends TestCase {
 public function testNativeLuaConvenienceAndStructuredViewsAgree(): void {
  $class = new \ReflectionClass(\ProfessionalWiki\SWS\EntryPoints\Scribunto\SubjectDataLookup::class);
  $lookup = $class->newInstanceWithoutConstructor();
  $entry = ['url'=>'https://example.org/?a=1&b=2','label'=>'Title','caption'=>'Source','captionPage'=>'Vermerk:Quelle'];
  $value = new UrlValue([$entry]);
  $this->assertSame($entry['url'],$class->getMethod('convertToScalar')->invoke($lookup,$value));
  $this->assertSame([1=>$entry['url']],$class->getMethod('convertToLuaTable')->invoke($lookup,$value));
  $this->assertSame([1=>$entry],$class->getMethod('valuesToLuaArray')->invoke($lookup,$value));
 }
 public function testNeo4jProjectionPreservesTheWholeRecord(): void {
  $entry = ['url'=>'https://example.org','label'=>'Title','caption'=>'Source','captionPage'=>'Vermerk:Quelle'];
  $registry = \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry::withCoreBuilders();
  $stored = $registry->buildNeo4jValue('url',new UrlValue([$entry]));
  $this->assertSame([$entry['url']],$stored);
  $class = new \ReflectionClass(\ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdater::class);
  $updater = $class->newInstanceWithoutConstructor();
  $class->getProperty('valueBuilderRegistry')->setValue($updater,$registry);
  $statement = new \ProfessionalWiki\SWS\Domain\Statement(new \ProfessionalWiki\SWS\Domain\Schema\PropertyName('Source'),'url',new UrlValue([$entry]));
  $props = $updater->statementsToNodeProperties(new \ProfessionalWiki\SWS\Domain\Subject\StatementList([$statement]));
  $this->assertSame([$entry['url']],$props['Source']);
  $this->assertSame(['Source'=>[$entry]],json_decode($props['#sws:urlEntries'],true,512,JSON_THROW_ON_ERROR));
 }
 public function testLegacyStringsAndRecordsPreserveTheirAssociation(): void {
  $record = ['url'=>'https://example.org/?a=1&b=2#x','label'=>'Title','caption'=>'Source','captionPage'=>'Vermerk:Quelle'];
  $value = new UrlValue(['https://legacy.org',$record]);
  $this->assertSame(ValueType::Url,$value->getType());
  $this->assertSame([['url'=>'https://legacy.org'],$record],$value->toScalars());
  $this->assertSame(['https://legacy.org',$record['url']],$value->urls());
  $this->assertSame($value->toScalars(),(new UrlValue($value->toScalars()))->toScalars());
 }
 public function testEmptyOptionalValuesAreOmitted(): void {
  $this->assertSame([['url'=>'https://example.org']],(new UrlValue([['url'=>' https://example.org ','label'=>' ']]))->parts);
  $this->assertTrue((new UrlValue(['']))->isEmpty());
 }
 public function testRejectsLabelWithoutAddress(): void {
  $this->expectException(InvalidArgumentException::class);new UrlValue([['url'=>'','label'=>'Keep me']]);
 }
 public function testRejectsUnknownMetadataInsteadOfLosingIt(): void {
  $this->expectException(InvalidArgumentException::class);new UrlValue([['url'=>'https://example.org','unknown'=>'Keep me']]);
 }
 public function testRejectsNonstrings(): void {
  $this->expectException(InvalidArgumentException::class);new UrlValue([['url'=>'https://example.org','caption'=>false]]);
 }
 public function testRejectsObjectInsteadOfList(): void {
  $this->expectException(InvalidArgumentException::class);new UrlValue(['url'=>'https://example.org']);
 }
}
