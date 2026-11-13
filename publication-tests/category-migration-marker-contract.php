<?php
declare(strict_types=1);
require __DIR__.'/category-contract.php';

use MediaWiki\Parser\ParserOutput;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\Property\PageProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\EntryPoints\Processing\CategoryAssignments;

$markerChecks=0;
function markerCheck(bool $ok,string $why):void{global $markerChecks;$markerChecks++;if(!$ok)throw new RuntimeException($why);}
$category=PageProperty::fromPartialJson(new PropertyCore('',false,null),['pageNamespace'=>14,'assignCategories'=>true,'multiple'=>true]);
$schema=schema($category);
$main=subject('AufEinWort:Schema Person','Kategorie:Namensraum:Thema','https://example.org','Kategorie:Mit#Abschnitt','w:Seite');
$before=serialize($main->getStatements()->asArray());$out=new ParserOutput();
$out->addCategory('Vorhanden','Originalsortierung');
(new CategoryAssignments())->apply($out,$schema,new PageSubjects($main,new SubjectMap()),$main,static fn()=>throw new RuntimeException('Unexpected relation lookup'));
markerCheck($out->categories===['Vorhanden'=>'Originalsortierung','SWS_Migration_Kategorie_ungültig'=>'','Namensraum:Thema'=>''],'Malformed assignments mark host, valid internal-colon category survives');
markerCheck(serialize($main->getStatements()->asArray())===$before,'Original malformed field values are preserved byte-for-byte');
markerCheck(!isset($out->categories['AufEinWort:Schema_Person']),'Missing category prefix is neither guessed nor executed');
markerCheck(count(array_filter(array_keys($out->categories),static fn($name)=>$name==='SWS_Migration_Kategorie_ungültig'))===1,'Multiple bad values create only one marker');
$clean=subject('Kategorie:Namensraum:Thema');$fresh=new ParserOutput();
(new CategoryAssignments())->apply($fresh,$schema,new PageSubjects($clean,new SubjectMap()),$clean,static fn()=>null);
markerCheck($fresh->categories===['Namensraum:Thema'=>''],'Fresh processing after repair removes automatic marker');
$inherited=PageProperty::fromPartialJson(new PropertyCore('',false,['AufEinWort:Schema Personendaten']),['pageNamespace'=>14,'assignCategories'=>true]);
$empty=subject();$fresh=new ParserOutput();
(new CategoryAssignments())->apply($fresh,schema($inherited),new PageSubjects($empty,new SubjectMap()),$empty,static fn()=>null);
markerCheck($fresh->categories===['AufEinWort:Schema_Personendaten'=>''],'Valid schema default with inner colon is not marked');
$existing=new ParserOutput();$existing->addCategory('SWS_Migration_Kategorie_ungültig','Eigene Sortierung');
(new CategoryAssignments())->apply($existing,$schema,new PageSubjects($main,new SubjectMap()),$main,static fn()=>null);
markerCheck($existing->categories['SWS_Migration_Kategorie_ungültig']==='Eigene Sortierung','Existing source category sort key preserved');
$plain=PageProperty::fromPartialJson(new PropertyCore('',false,null),['pageNamespace'=>14,'assignCategories'=>false]);$fresh=new ParserOutput();
(new CategoryAssignments())->apply($fresh,schema($plain),new PageSubjects($main,new SubjectMap()),$main,static fn()=>null);
markerCheck($fresh->categories===[],'Ordinary page links neither categorize nor trigger marker');
echo "$markerChecks migration marker/inner-colon/preserved-input/cleanup/default/sort-key checks passed with actual category processor and controlled titles.\n";
