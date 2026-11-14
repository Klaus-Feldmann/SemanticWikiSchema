<?php
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Semantic\{SmwMappingConfig,AnnotationPlanner};

final class SmwMappingConfigTest extends TestCase {
    public function testDefaultsHaveNoOldWerkExceptions(): void {
        $config = SmwMappingConfig::resolve([]);
        self::assertSame([], $config['properties']);
        self::assertSame('WikiSchema-Feld', $config['urlEntries']['field']);
        self::assertSame('WikiSchema-Subjekt', $config['urlEntries']['subject']);
        foreach (['Leserschaft','Leserschaftziel','Werkausgabe'] as $field) {
            self::assertSame(['Werk:'.$field.'=Person'], (new AnnotationPlanner($config))->build([
                'schema'=>['name'=>'Werk'], 'subject'=>['id'=>'s1'],
                'fields'=>[['name'=>$field,'type'=>'text','values'=>[['value'=>'Person']],'hasStoredValues'=>true]],
            ])->rows);
        }
    }
    public function testPartialSettingsPreserveDefaultsAndOwnMappings(): void {
        $config = SmwMappingConfig::resolve(['properties'=>['Person'=>['Feld'=>'Eigene Benennung']], 'urlEntries'=>['subject'=>'Subjektkennung']]);
        self::assertSame('Quellen-URL', $config['urlEntries']['url']);
        self::assertSame('Subjektkennung', $config['urlEntries']['subject']);
        self::assertSame(['Person'=>['Feld'=>'Eigene Benennung']], $config['properties']);
        self::assertSame(['Eigene Benennung=Wert'], (new AnnotationPlanner($config))->build([
            'schema'=>['name'=>'Person'], 'subject'=>['id'=>'s1'],
            'fields'=>[['name'=>'Feld','type'=>'text','values'=>[['value'=>'Wert']],'hasStoredValues'=>true]],
        ])->rows);
    }
    public function testUrlSubobjectsKeepMetadataAndUseConfiguredNames(): void {
        $cases=json_decode(file_get_contents(__DIR__.'/semantic/cases.json'),true);
        foreach($cases as $case) { if($case['name']==='url-structured') { $c=$case;break; } }
        $reader=new \ProfessionalWiki\SWS\Application\Semantic\FieldValues($c['schema'],$c['subject'],fn()=>$c['external']);
        $plan=(new AnnotationPlanner(SmwMappingConfig::resolve([])))->build($reader->collect());
        self::assertSame(['Wikipedia (de)=https://de.wikipedia.org/wiki/Regiomontanus?a=1&b=2'],$plan->rows);
        self::assertContains('WikiSchema-Feld=Feld',$plan->objects[0]);
        self::assertContains('WikiSchema-Subjekt='.$c['subject']['id'],$plan->objects[0]);
        $plan=(new AnnotationPlanner(SmwMappingConfig::resolve(['urlEntries'=>['subject'=>'Subjektkennung']])))->build($reader->collect());
        self::assertContains('Subjektkennung='.$c['subject']['id'],$plan->objects[0]);
    }
    /** @dataProvider invalidSettings */
    public function testInvalidSettingsAreRejected(mixed $config): void {
        $this->expectException(InvalidArgumentException::class);
        SmwMappingConfig::resolve($config);
    }
    public static function invalidSettings(): array {
        return [[null],['text'],[['unknown'=>[]]],[['properties'=>false]],[['properties'=>['Werk'=>false]]],
            [['properties'=>['Werk'=>['Feld'=>'']]]],[['properties'=>['Werk'=>['Feld'=>'A::B']]]],
            [['urlEntries'=>['unknown'=>'Name']]],[['urlEntries'=>['field'=>false]]],
            [['urlEntries'=>['field'=>'x=y']]]];
    }
}
