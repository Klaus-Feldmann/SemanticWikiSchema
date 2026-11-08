<?php

declare(strict_types=1);

require __DIR__ . '/autoload.php';

use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\HistoricalDateType;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Rdf\RdfValueMapperRegistry;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jValueBuilderRegistry;

$count = 0;
function same(mixed $actual, mixed $expected, string $message): void {
    global $count;
    if ($actual !== $expected) {
        throw new RuntimeException($message . ': ' . var_export($actual, true));
    }
    $count++;
}

foreach (['1458','1458-10','1458-10-01','-0044','-0044-03-15','-0001','-0001-02-29','0001','2024-02-29','9999-12-31','-9999'] as $raw) {
    same(HistoricalDate::parse($raw) !== null, true, 'Valid date: ' . $raw);
}
foreach (['','0000','-0000','0000-01-01','-0000-01-01','44','-44','+0044',' 1458','1458 ','1458-1','1458-01-1','1458-00','1458-13','1458-01-00','1458-01-32','1458-04-31','2025-02-29','1900-02-29','-0044-02-29','10000','-10000',"1458\n",'1458-10-01T00:00:00Z'] as $raw) {
    same(HistoricalDate::parse($raw), null, 'Invalid date: ' . $raw);
}
same(HistoricalDate::parse('1458')->bounds(), ['start'=>2253593,'end'=>2253957], 'Year precision');
same(HistoricalDate::parse('2024-02')->bounds(), ['start'=>2460342,'end'=>2460370], 'Month precision');
same(HistoricalDate::parse('-0001-12-31')->bounds()['end'] < HistoricalDate::parse('0001-01-01')->bounds()['start'], true, 'Era transition');
$registry = new PropertyTypeRegistry();
$type = new HistoricalDateType();
$registry->registerType($type);
$registry->registerType(new TextType());
$link = ['namespace'=>'Werk','labelTarget'=>'Datierung','values'=>true];
foreach (['historicalDate','text'] as $name) {
    $property = PropertyDefinition::fromJson(['type'=>$name,'wikiLink'=>$link], $registry);
    same($property->toJson()['wikiLink'], $link, 'Display metadata roundtrip');
    same(PropertyDefinition::fromJson($property->toJson(),$registry)->getWikiLink(),$link,'Read back');
}
$property = PropertyDefinition::fromJson(['type'=>'historicalDate'], $registry);
same($type->validate(new StringValue('-0044'),$property),[],'BCE validation');
same(count($type->validate(new StringValue('0000'),$property)),1,'Reject year zero');
same(count($type->validate(new StringValue('1458','1459'),$property)),1,'Reject multiple dates');
foreach (['-0044',['-0044'],[]] as $default) {
    $p = PropertyDefinition::fromJson(['type'=>'historicalDate','default'=>$default],$registry);
    same($p->toJson()['default'],$default,'Default serialization');
}
foreach ([['values'=>'yes'],['unknown'=>true]] as $invalid) {
    try {
        PropertyDefinition::fromJson(['type'=>'text','wikiLink'=>$invalid],$registry);
        throw new RuntimeException('Invalid display metadata was accepted');
    } catch (InvalidArgumentException $e) {
        $count++;
    }
}
$literals = RdfValueMapperRegistry::withCoreMappers()->mapValue('historicalDate',new StringValue('-0044'));
same(count($literals),1,'RDF value exists');
same($literals[0]->lexicalForm,'-0044','RDF preserves BCE');
same($literals[0]->datatype->value,HistoricalDate::RDF_DATATYPE,'RDF declares historical year convention');
same(Neo4jValueBuilderRegistry::withCoreBuilders()->buildNeo4jValue('historicalDate',new StringValue('1458')),['1458'],'Neo4j preserves precision');
echo $count . " backend checks passed on PHP " . PHP_VERSION . "\n";
