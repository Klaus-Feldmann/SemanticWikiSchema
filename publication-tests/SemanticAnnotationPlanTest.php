<?php
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Semantic\{FieldSnapshot,FieldValues,AnnotationPlanner,SmwValueFormatter};
use ProfessionalWiki\SWS\Domain\Schema\{Schema,SchemaName,SchemaReference,PropertyDefinitions,PropertyCore,PropertyName};
use ProfessionalWiki\SWS\Domain\Schema\Property\{TextProperty,SelectProperty,SelectOption};
use ProfessionalWiki\SWS\Domain\Subject\{Subject,SubjectId,StatementList};
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\{StringValue,NumberValue,BooleanValue,UrlValue};

final class SemanticAnnotationPlanTest extends TestCase {
    private function fixture(string $name): array {
        foreach (json_decode(file_get_contents(__DIR__.'/semantic/cases.json'),true) as $case) { if ($case['name']===$name) { return $case; } }
        throw new RuntimeException('Missing fixture');
    }
    private function reader(array $c, ?Closure $fetch = null): FieldValues {
        return new FieldValues($c['schema'],$c['subject'],$fetch ?? fn()=>$c['external']);
    }
    public function testDomainSnapshotUsesRealValuesAndSchema(): void {
        $core = new PropertyCore('',false,null);
        $schema = new Schema(new SchemaName('Person'),'Example',new PropertyDefinitions(['Art'=>new SelectProperty($core,[new SelectOption('o1','Abhandlung')],false)]),false);
        $subject = new Subject(new SubjectId('sF1FCQPNcwQdw4B'),null,SchemaReference::local(new SchemaName('Person')),new StatementList([new Statement(new PropertyName('Art'),'select',new StringValue('o1'))]));
        $result = FieldSnapshot::reader($schema,$subject,fn()=>throw new RuntimeException('No fetch expected'))->collect();
        self::assertSame('Abhandlung',$result['fields'][0]['values'][0]['value']);
        self::assertSame(['Art=Abhandlung'],(new AnnotationPlanner($this->fixture('empty')['config']))->build($result)->rows);
    }
    public function testZeroAndFalseSurvive(): void {
        foreach (['number-3'=>'Feld=0','boolean-5'=>'Feld=0','boolean-6'=>'Feld=1'] as $name=>$expected) {
            self::assertSame([$expected],(new AnnotationPlanner($this->fixture('empty')['config']))->build($this->reader($this->fixture($name))->collect())->rows);
        }
    }
    public function testOwnValuePrecedenceAndSingleSourceSnapshotRead(): void {
        $c=$this->fixture('wikidata-own');$calls=0;
        $reader=$this->reader($c,function() use($c,&$calls) { $calls++;return $c['external']; });
        self::assertSame('Eigene Angabe',$reader->collect()['fields'][0]['values'][0]['value']);
        // The supplied callback reads the authorized source snapshot; it is not a network transport.
        self::assertSame(1,$calls);
        self::assertSame('Mensch',$reader->collect(null,'external')['fields'][0]['values'][0]['value']);
        $reader->collect(null,'external');self::assertSame(1,$calls);
        self::assertSame('Eigene Angabe',$reader->collect(null,'own')['fields'][0]['values'][0]['value']);
    }
    public function testDatePrecisionCalendarAndOffset(): void {
        $planner=new AnnotationPlanner($this->fixture('empty')['config']);
        foreach (['historical--0347'=>'Feld=347 BC Jl','historical-1582-10-04'=>'Feld=4 October 1582 Jl','historical-1582-10-15'=>'Feld=15 October 1582 Gr','datetime-2026-11-04T00:15:00+05:45'=>'Feld=3 November 2026 18:30:00+00:00 Gr','datetime-2026-12-31T23:59:59.123456789-02:00'=>'Feld=1 January 2027 01:59:59+00:00 .123456789 Gr'] as $name=>$expected) {
            self::assertSame([$expected],$planner->build($this->reader($this->fixture($name))->collect())->rows);
        }
    }
    public function testUrlIdentityAndMetadataDoNotBecomeWikitext(): void {
        $plan=(new AnnotationPlanner($this->fixture('empty')['config']))->build($this->reader($this->fixture('url-structured'))->collect());
        self::assertSame(['Wikipedia (de)=https://de.wikipedia.org/wiki/Regiomontanus?a=1&b=2'],$plan->rows);
        self::assertContains('Quellentitel=Regiomontanus',$plan->objects[0]);
        self::assertContains('Quellenbezeichnungsseite=Vermerk:Wikipedia (de)',$plan->objects[0]);
    }
    public function testUnavailableSourceDoesNotDiscardOtherFields(): void {
        $c=$this->fixture('remote-unavailable');
        array_unshift($c['schema']['properties'],['name'=>'Vorher','type'=>'text']);
        $c['subject']['statements']['Vorher']=['values'=>['Gut']];
        $plan=(new AnnotationPlanner($this->fixture('empty')['config']))->build($this->reader($c)->collect());
        self::assertSame(['Vorher=Gut'],$plan->rows);
    }
    public function testExistingMappingsPreserved(): void {
        foreach (['Leserschaft','Leserschaftziel','Werkausgabe'] as $name) {
            self::assertSame(['hat '.$name.'=Person'],(new AnnotationPlanner($this->fixture('empty')['config']))->build($this->reader($this->fixture('no-old-map-'.$name))->collect())->rows);
        }
    }
    public function testEmptyAndSkippedFields(): void {
        $empty=$this->fixture('empty');
        $empty['subject']['statements']['Feld']['values']=['',null];
        self::assertSame([],(new AnnotationPlanner($empty['config']))->build($this->reader($empty)->collect())->rows);
        foreach (['image-7','wikitext-8','remote-manual','remote-missing'] as $name) {
            self::assertSame([],(new AnnotationPlanner($this->fixture('empty')['config']))->build($this->reader($this->fixture($name))->collect())->rows);
        }
    }
    public function testPlainTextWhitespaceIsNotRewrittenInTheReadModel(): void {
        $case=$this->fixture('empty');
        $result=$this->reader($case)->collect();
        self::assertSame('  ',$result['fields'][0]['values'][0]['value']);
        // Planner rows are parser input; preserving raw text is not proof SMW stores an empty value.
        self::assertSame(['Feld=  '],(new AnnotationPlanner($case['config']))->build($result)->rows);
    }
    public function testSnapshotIsNotChangedByCollecting(): void {
        $c=$this->fixture('url-structured');$before=$c;$this->reader($c)->collect();self::assertSame($before,$c);
    }
    public function testAllUrlNamesRemainCustomizable(): void {
        foreach (['url','label','caption','captionPage','field','subject'] as $key) {
            $c=$this->fixture('renamed-url-'.$key);
            $plan=(new AnnotationPlanner($c['config']))->build($this->reader($c)->collect());
            $found=false;
            foreach ($plan->objects[0] as $row) { if (str_starts_with($row,'Eigene '.$key.'=')) { $found=true; } }
            self::assertTrue($found,$key);
        }
    }
    public function testSchemaSpecificMapAndMergedDuplicates(): void {
        $c=$this->fixture('custom-field-map');
        self::assertSame(['Eigene Eigenschaft=Wert'],(new AnnotationPlanner($c['config']))->build($this->reader($c)->collect())->rows);
        $c=$this->fixture('merged-properties');
        self::assertSame(['Gemeinsam=A','Gemeinsam=B','Gemeinsam=C'],(new AnnotationPlanner($c['config']))->build($this->reader($c)->collect())->rows);
    }
    public function testConfigIsReadOnlyAndCaptionRetainsPrecedence(): void {
        $c=$this->fixture('url-structured');
        $c['config']['properties']['Person']['Feld']='Andere Eigenschaft';$before=$c['config'];
        $plan=(new AnnotationPlanner($c['config']))->build($this->reader($c)->collect());
        self::assertSame($before,$c['config']);
        self::assertSame(['Wikipedia (de)=https://de.wikipedia.org/wiki/Regiomontanus?a=1&b=2'],$plan->rows);
    }

}
