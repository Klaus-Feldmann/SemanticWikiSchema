<?php
declare(strict_types=1);
require __DIR__.'/identity-projections-contract.php';
use ProfessionalWiki\SWS\Domain\Layout\LayoutName;
use ProfessionalWiki\SWS\Persistence\MediaWiki\{LayoutPersistenceDeserializer,LayoutContentValidator};
use ProfessionalWiki\SWS\Presentation\LayoutPresentationSerializer;
use ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName;
$nLayout=0;function layoutCheck(bool $ok,string $why):void{global$nLayout;$nLayout++;if(!$ok)throw new RuntimeException($why);}
$reader=new LayoutPersistenceDeserializer($schemaParser);
$explicit=$reader->deserialize(new LayoutName('New'),' {"schema":{"id":"S1"},"type":"table"}');
$named=$reader->deserialize(new LayoutName('Old'),' {"schema":"S1","type":"table"}');
layoutCheck($explicit->getSchemaReference()->id?->text==='S1'&&$named->getSchemaReference()->id===null,'Layout distinguishes typed ID from a same-spelled legacy name');
layoutCheck(json_decode((new LayoutPresentationSerializer())->serialize($explicit),true)['schema']===['id'=>'S1'],'Layout REST serialization preserves explicit ID');
$validator=LayoutContentValidator::newInstance();
layoutCheck($validator->validate('{"schema":{"id":"S1"},"type":"table"}')&&$validator->validate('{"schema":"Person","type":"table"}'),'Old and new local layout formats validate');
layoutCheck(!$validator->validate('{"schema":{"id":"S01"},"type":"table"}'),'Malformed schema IDs cannot be saved in layouts');
layoutCheck(SubjectDisplayName::inPlaceOfPageTitle($page->getSubjects(),'Subject:'.ucfirst($old),fn($id)=>$canonical->parseOrThrow($id)->text)===$subject,'Current migrated entity keeps its own label even at a legacy free-entity page URL');
echo "$nLayout D6 layout/reference/validation/legacy-URL-label checks passed.\n";
