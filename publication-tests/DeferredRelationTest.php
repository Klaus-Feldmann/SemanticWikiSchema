<?php
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Semantic\{FieldValues,AnnotationPlanner};
final class DeferredRelationTest extends TestCase {
    private function reader(array $relations, string $type='relation'): FieldValues {
        return new FieldValues(['name'=>'Person','properties'=>[
            ['name'=>'Name','type'=>'text'],
            ['name'=>'Forschung','type'=>$type,'targetSchemas'=>['Forschungsache']],
            ['name'=>'Zahl','type'=>'number'],['name'=>'Wahr','type'=>'boolean'],
        ]],['id'=>'sMain','statements'=>[
            'Name'=>['values'=>['Archelaos']], 'Forschung'=>['values'=>$relations],
            'Zahl'=>['values'=>[0]],'Wahr'=>['values'=>[false]],
        ]],fn()=>throw new RuntimeException('No external fetch or target resolution expected'));
    }
    private function rows(array $result): array { return (new AnnotationPlanner(['properties'=>[]]))->build($result)->rows; }
    public function testRelationPreservesIdentityAndDoesNotBlockOtherAnnotations(): void {
        $raw=[['id'=>'rOne','target'=>'sChild']];$result=$this->reader($raw)->collectForAnnotations();
        self::assertSame(['Person:Name=Archelaos','Person:Zahl=0','Person:Wahr=0'],$this->rows($result));
        $field=$result['fields'][1];self::assertSame('Forschung',$field['name']);
        self::assertSame(['Forschungsache'],$field['targetSchemas']);
        self::assertSame($raw,$field['rawStatement']['values']);
        self::assertSame('deferred-relation',$field['status']);
        self::assertSame([],$field['values']);
    }
    public function testMultipleAndEmptyRelations(): void {
        foreach([[],[['id'=>'rOne','target'=>'sOne'],['id'=>'rTwo','target'=>'sTwo']]] as $raw) {
            self::assertSame(['Person:Name=Archelaos','Person:Zahl=0','Person:Wahr=0'],$this->rows($this->reader($raw)->collectForAnnotations()));
        }
    }
    public function testUnsupportedStructureInTextStillFails(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported scalar');
        $this->reader([['unexpected'=>'array']],'text')->collectForAnnotations();
    }
    public function testDefaultReadContractIsUnchanged(): void {
        $this->expectException(InvalidArgumentException::class);
        $this->reader([['id'=>'rOne','target'=>'sChild']])->collect();
    }
    public function testPreviouslySupportedRelationLabelReadStillWorks(): void {
        $result=$this->reader(['Existing display label'])->collect('Forschung');
        self::assertSame('Existing display label',$result['fields'][0]['values'][0]['value']);
    }
    public function testStructuredUrlIsNotMistakenForRelation(): void {
        $reader=new FieldValues(['name'=>'Person','properties'=>[['name'=>'Quelle','type'=>'url']]],
            ['id'=>'sMain','statements'=>['Quelle'=>['values'=>[['url'=>'https://example.org/?a=1&b=2']]]]],fn()=>[]);
        $field=$reader->collectForAnnotations()['fields'][0];
        self::assertSame('https://example.org/?a=1&b=2',$field['values'][0]['value']);
        self::assertSame('own',$field['status']);
    }
}
