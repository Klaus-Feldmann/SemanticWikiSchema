<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

require __DIR__ . '/Cases.php';
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingResult;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;
$schema['propertyDefinitions']['Quellenangabe']=['type'=>'url'];
saveTestPage('Attribut:Quellenangabe',new WikitextContent('[[Datentyp::URL]]'));
saveTestPage('Attribut:Quellen-URL',new WikitextContent('[[Datentyp::URL]]'));
saveTestPage('Schema:ProcessingTest',new SchemaContent(json_encode($schema)));
$subject['subjects']['sProcessingTest']['statements']=['Name'=>['type'=>'text','value'=>['Privater Wert']],
 'Quellenangabe'=>['type'=>'url','value'=>[['url'=>'https://example.org/page?a=1&b=2','label'=>'Beispiel']]]];
saveTestPage('Processing fixture',new WikitextContent('Private article. [[Manuell::Erhalten]]'),new SubjectContent(json_encode($subject)));
$processor->refresh($title);DeferredUpdates::doUpdates();
$stored=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
checkTest(count($stored->getSubSemanticData())===1,'URL subobject survives complete update');
checkTest(count(dataValues($title,'Quellenangabe'))===1,'URL with query parameters survives complete update');
// A valid revision stamp attached to an altered semantic container is not enough.
$result=$processor->prepare($title);
$data=$result->output->getExtensionData('smwdata');
$data->removeProperty(SMW\DataItems\Property::newFromUserLabel('Name'));
try {SMW\Services\ServicesFactory::getInstance()->getStore()->updateData($data);throw new RuntimeException('tampered snapshot accepted');}catch(ProcessingIncomplete $e){}
checkTest(dataValues($title,'Name')===['Privater Wert'],'copied completion stamp cannot authorize partial data');
$job=$services->getJobFactory()->newJob('smw.update',$title,['forcedUpdate'=>true]);
checkTest($job->run(),'real SMW UpdateJob executes in private wiki');DeferredUpdates::doUpdates();
checkTest(dataValues($title,'Name')===['Privater Wert'],'SMW UpdateJob retains source-derived assertions');
// Removing the subject slot is also intentional and must clear stale SWS-derived assertions.
$page=$services->getWikiPageFactory()->newFromTitle($title);$updater=$page->newPageUpdater($admin);
$updater->removeSlot('sws');$updater->setContent('main',new WikitextContent('Remaining ordinary article. [[Manuell::Erhalten]]'));
$updater->saveRevision(MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment('remove test subject'),0);DeferredUpdates::doUpdates();
$processor->refresh($title);DeferredUpdates::doUpdates();
checkTest(dataValues($title,'Name')===[] && isset(storedProps($title)['Manuell']),'subject removal clears derived data and retains manual data');
echo "ALL ADDITIONAL CASES PASSED\n";
