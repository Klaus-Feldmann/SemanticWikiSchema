<?php
require __DIR__.'/nonwikitext-annotations-contract.php';
// Actual config reader -> planner -> SMW adapter, including property/message dependencies.
$GLOBALS['swsTestUiLanguage']='en';
$GLOBALS['swsTestMessages']['de']['sws-smw-property-description']='Zusammenfassung';
$out=apply($t,null,$described,subject('Unabhängiger Schemawert'),'Aktiver Rang');
check($out->getExtensionData('smwdata')->rows===['Zusammenfassung=Aktiver Rang','Kurzbeschreibung=Unabhängiger Schemawert'],'Changed system name preserves independent schema annotation');
check(in_array('8:sws-smw-property-description',$out->dependencies,true),'Used message has a real template dependency');
check(in_array('102:Zusammenfassung',$out->dependencies,true),'New property definition tracked');
check(!in_array('8:sws-smw-property-entity',$out->dependencies,true),'Unused URL names do not trigger whole-wiki dependency fanout');
$GLOBALS['swsTestMessages']['de']['sws-smw-property-description']='Ungültig=Name';
try { apply($t,null,$described,subject('Bewahren'),'Wert'); check(false,'Invalid name accepted'); }
catch (InvalidArgumentException) { check(true,'Invalid configured name refuses new annotation plan'); }
echo "Actual named annotation projection, independent value preservation and bounded message dependencies passed.\n";
