<?php
use PHPUnit\Framework\TestCase;
use SWSBaseBridge\{TextClaimView,PropertyDatatypePolicy,PropertyResults,SchemaPropertyBindings};
use ProfessionalWiki\SWS\Application\Semantic\{FieldValues,AnnotationPlanner};
final class SourceTextValuesTest extends TestCase {
    private function reader(array $values,string $status='resolved',array $own=[]): FieldValues {
        return new FieldValues(['name'=>'Person','properties'=>[
            ['name'=>'Geburtsname','type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P1477']],
            ['name'=>'Kontrolle','type'=>'text']]],['id'=>'sTest','statements'=>[
                'Geburtsname'=>['values'=>$own],'Kontrolle'=>['values'=>['Bleibt']]]],
            fn()=>['version'=>1,'scope'=>'current','mainSubjectId'=>'sTest','fields'=>[
                ['name'=>'Geburtsname','source'=>'wikidata','propertyId'=>'P1477','status'=>$status,'values'=>$values]]]);
    }
    private function rows(FieldValues $reader): array { return (new AnnotationPlanner(['properties'=>[]]))->build($reader->collectForAnnotations())->rows; }
    public function testRecordedWikidataNames(): void {
        $claims=json_decode(file_get_contents(__DIR__.'/semantic/source-texts.json'),true);
        foreach(['Q9049'=>['Avram Noam Chomsky','en'],'Q43746'=>['Giovanni Fidanza','it'],'Q76509'=>['Karl Theodor Jaspers','de']] as $id=>[$text,$language]) {
            $values=TextClaimView::values($claims[$id],'monolingualtext');
            self::assertSame($text,$values[0]['value']);self::assertSame($language,$values[0]['language']);
            self::assertSame(['Person:Geburtsname='.$text,'Person:Kontrolle=Bleibt'],$this->rows($this->reader($values)));
        }
        self::assertSame([],TextClaimView::values($claims['Q188332'],'monolingualtext'));
    }
    public function testEmptyAndExplicitUnknownValuesNeverBecomeAnnotations(): void {
        foreach([['missing',[]],['resolved',[['kind'=>'somevalue']]],['resolved',[['kind'=>'novalue']]]] as [$status,$values]) {
            self::assertSame(['Person:Kontrolle=Bleibt'],$this->rows($this->reader($values,$status)));
        }
        $values=TextClaimView::values([['mainsnak'=>['snaktype'=>'somevalue']],['mainsnak'=>['snaktype'=>'novalue']]],'monolingualtext');
        self::assertSame([['kind'=>'somevalue'],['kind'=>'novalue']],$values);
    }
    public function testOwnTextWins(): void {
        self::assertSame(['Person:Geburtsname=Eigene Angabe','Person:Kontrolle=Bleibt'],$this->rows($this->reader([], 'unavailable',['Eigene Angabe'])));
    }
    public function testUnavailableDoesNotBlockOtherFields(): void {
        self::assertSame(['Person:Kontrolle=Bleibt'],$this->rows($this->reader([],'unavailable')));
    }
    public function testMultipleLanguagesAndRanksStayInReadValues(): void {
        $claims=[];
        foreach(['en','de','it'] as $lang) {$claims[]=['rank'=>'normal','mainsnak'=>['snaktype'=>'value','datatype'=>'monolingualtext','datavalue'=>['value'=>['text'=>'Name','language'=>$lang]]]];}
        $values=TextClaimView::values($claims,'monolingualtext');self::assertSame(['en','de','it'],array_column($values,'language'));
        self::assertSame(['normal','normal','normal'],array_column($values,'rank'));
    }
    public function testMalformedTextIsNotReportedAsMissing(): void {
        $this->expectException(RuntimeException::class);
        TextClaimView::values([['mainsnak'=>['snaktype'=>'value','datatype'=>'monolingualtext','datavalue'=>['value'=>['text'=>'Name']]]]],'monolingualtext');
    }
    public function testCompatibilityMatrix(): void {
        foreach(['wikibase-item','monolingualtext','string','external-id'] as $type) {self::assertTrue(PropertyDatatypePolicy::supports('text',$type));}
        self::assertTrue(PropertyDatatypePolicy::supports('historicalDate','time'));
        self::assertTrue(PropertyDatatypePolicy::supports('image','commonsMedia'));
        self::assertFalse(PropertyDatatypePolicy::supports('historicalDate','monolingualtext'));
        self::assertFalse(PropertyDatatypePolicy::supports('text','time'));
        self::assertFalse(PropertyDatatypePolicy::supports('number','monolingualtext'));
    }
    public function testPropertyResolutionExposesActualDatatype(): void {
        $result=PropertyResults::fromEntity(['entities'=>['P1477'=>['id'=>'P1477','type'=>'property','datatype'=>'monolingualtext']]],'P1477','de',fn($id)=>'https://www.wikidata.org/wiki/Property:'.$id);
        self::assertSame('monolingualtext',$result[0]['datatype']);
    }
    public function testNewAndChangedSchemaBindingsAreValidatedBeforeSave(): void {
        $schema=['propertyDefinitions'=>['Geburtsname'=>['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P1477']]]];
        $lookup=static fn()=>['P1477'=>['datatype'=>'monolingualtext']];
        SchemaPropertyBindings::validate($schema,[],null,$lookup);
        $bad=$schema;$bad['propertyDefinitions']['Geburtsname']['externalProperty']['id']='P569';
        try {SchemaPropertyBindings::validate($bad,[],null,static fn()=>['P569'=>['datatype'=>'time']]);self::fail('incompatible mapping accepted');}
        catch (RuntimeException $e) {self::assertStringContainsString('time',$e->getMessage());}
        $corrected=$bad;$corrected['propertyDefinitions']['Geburtsname']['type']='historicalDate';
        SchemaPropertyBindings::validate($corrected,[],null,static fn()=>['P569'=>['datatype'=>'time']]);
    }
    public function testUnchangedBindingsRemainEditableDuringTemporarySourceOutage(): void {
        $schema=['propertyDefinitions'=>['Geburtsname'=>['type'=>'text','externalProperty'=>['source'=>'wikidata','id'=>'P1477']]]];
        $called=false;
        SchemaPropertyBindings::validate($schema,$schema,null,static function() use (&$called){$called=true;throw new RuntimeException('should not query unchanged mapping');});
        self::assertFalse($called);
    }
}
