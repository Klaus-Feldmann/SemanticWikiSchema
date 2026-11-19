<?php
use PHPUnit\Framework\TestCase;
use AEWI\SWS\Presentation\ExportFileName;
use AEWI\SWS\Application\Semantic\SmwMappingConfig;

final class NamingPresentationTest extends TestCase {
    public function testDownloadTemplateKeepsIdentityAndEncodesForeignId():void {
        self::assertSame('daten-E123',ExportFileName::fromTemplate('daten-$1','E123','sws-$1'));
        self::assertSame('daten-source%3AQ1',ExportFileName::fromTemplate('daten-$1','source:Q1','sws-$1'));
    }
    /** @dataProvider invalidNames */
    public function testInvalidDownloadTemplatesCannotInjectHeadersOrPaths(string $template):void {
        self::assertSame('sws-entity-E9-data-view',ExportFileName::fromTemplate($template,'E9','sws-entity-$1-data-view'));
    }
    public static function invalidNames():array {
        return [["bad\r\nHeader: $1"],['../$1'],['dir/$1'],['dir\\$1'],['"$1"'],['no-id'],['$1-$1'],['$2'],['$1 space'],[str_repeat('a',121).'$1']];
    }
    public function testTranslatedNamesDoNotReplaceExplicitSettings():void {
        $defaults=SmwMappingConfig::resolve([]);
        $names=['urlEntries'=>$defaults['urlEntries'],'descriptionProperty'=>'Zusammenfassung'];
        $names['urlEntries']['subject']='Einheitenkennung';
        $config=SmwMappingConfig::resolve(['urlEntries'=>['subject'=>'Eigene Kennung'],'descriptionProperty'=>'Eigene Beschreibung'],$names);
        self::assertSame('Eigene Kennung',$config['urlEntries']['subject']);
        self::assertSame('Eigene Beschreibung',$config['descriptionProperty']);
        self::assertSame('Quellen-URL',$config['urlEntries']['url']);
    }
    /** @dataProvider invalidProperties */
    public function testInvalidTranslatedNamesRefuseRatherThanPublishBadData(string $name):void {
        $defaults=SmwMappingConfig::resolve([]);$defaults['urlEntries']['subject']=$name;
        $this->expectException(InvalidArgumentException::class);
        SmwMappingConfig::resolve([],$defaults);
    }
    /** @dataProvider invalidProperties */
    public function testDescriptionNamesUseTheSameStrictPropertyValidator(string $name):void {
        $this->expectException(InvalidArgumentException::class);
        SmwMappingConfig::resolve(['descriptionProperty'=>$name]);
    }
    public static function invalidProperties():array { return [[''],['A=B'],['A|B'],['A::B'],["A\nB"],['[[A]]'],['+A'],['@json']]; }
}
