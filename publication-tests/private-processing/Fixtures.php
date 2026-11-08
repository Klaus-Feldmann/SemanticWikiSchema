<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIdentity;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingResult;
$services=MediaWikiServices::getInstance();
$admin=$services->getUserFactory()->newFromName('TestAdmin');
$GLOBALS['services']=$services; $GLOBALS['admin']=$admin;
function saveTestPage(string $name, $main, $neo=null) {
 global $services,$admin;
 $page=$services->getWikiPageFactory()->newFromTitle(Title::newFromText($name));
 $updater=$page->newPageUpdater($admin);$updater->setContent('main',$main);
 if ($neo!==null) {$updater->setContent('sws',$neo);}
 $rev=$updater->saveRevision(CommentStoreComment::newUnsavedComment('isolated private integration fixture'),0);
 if (!$rev && $updater->getStatus()->isOK()) {$rev=$page->getRevisionRecord();}
 if (!$rev) {throw new RuntimeException('Save failed: '.$name.' '.$updater->getStatus()->getWikiText());}
 DeferredUpdates::doUpdates();
 return $rev;
}
function checkTest($condition,$message) {if(!$condition)throw new RuntimeException('FAIL: '.$message); echo 'PASS '.$message."\n";}
$u=ProcessingIdentity::get();
checkTest($u->isSystemUser() && $u->isAllowed('read') && !$u->isAllowed('edit') && !$u->isAllowed('delete'),'noninteractive read-only identity');

$dir=$GLOBALS['IP'] . '/extensions/SemanticWikiSchema/integrations/smw/Wikiseiten/';
foreach (['SWS'=>'Modul-SWS.lua','SWS/API'=>'Modul-SWS-API.lua','SWS/config'=>'Modul-SWS-config.lua'] as $name=>$file) {
 saveTestPage('Modul:'.$name,new MediaWiki\Extension\Scribunto\ScribuntoContent(file_get_contents($dir.$file)));
}
saveTestPage('Vorlage:SWS',new WikitextContent(file_get_contents($dir.'Vorlage-SWS.wikitext')));

$schema=['title'=>'ProcessingTest','propertyDefinitions'=>[
 'Name'=>['type'=>'text'],
 'Programm'=>['type'=>'wikitext','execute'=>true,'wikitext'=>'{{SWS}}{{#set:Verarbeitungstest=Vollständig}}']
]];
saveTestPage('Schema:ProcessingTest',new SchemaContent(json_encode($schema)));
$subject=['mainSubject'=>'sProcessingTest','subjects'=>['sProcessingTest'=>['label'=>'Processing fixture','schema'=>'ProcessingTest','statements'=>['Name'=>['type'=>'text','value'=>['Privater Wert']]]]]];
saveTestPage('Processing fixture',new WikitextContent('Private article. [[Manuell::Erhalten]]'),new SubjectContent(json_encode($subject)));
$title=Title::newFromText('Processing fixture');
$result=(new InternalPageProcessor())->prepare($title);
checkTest($result->output->getCacheExpiry()===0,'privileged output not cacheable');
checkTest(ProcessingResult::isCurrent($title,$result->stamp),'complete revision snapshot validated');

(new InternalPageProcessor())->refresh($title);
DeferredUpdates::doUpdates();
$data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
echo 'PROPERTIES '.json_encode(array_keys($data->getProperties()),JSON_UNESCAPED_UNICODE)."\n";
checkTest(isset($data->getProperties()['Verarbeitungstest']),'schema annotation persisted in private wiki');
checkTest(isset($data->getProperties()['Manuell']),'manual annotation preserved');
checkTest(isset($data->getProperties()['Name']),'actual Modul:SWS annotations persisted');
