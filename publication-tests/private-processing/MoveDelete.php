<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

require __DIR__ . '/Fixtures.php';
$new=MediaWiki\Title\Title::newFromText('Processing fixture moved');
$move=$services->getMovePageFactory()->newMovePage($title,$new);
$status=$move->moveIfAllowed($admin,'isolated move test',true);
if (!$status->isOK()) {throw new RuntimeException('Move failed: '.json_encode($status->getErrors()));}
MediaWiki\Deferred\DeferredUpdates::doUpdates();
(new ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor())->refresh($new,$title);
MediaWiki\Deferred\DeferredUpdates::doUpdates();
$data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($new));
checkTest(isset($data->getProperties()['Name']),'moved page receives complete derived annotations');
$delete=$services->getDeletePageFactory()->newDeletePage($services->getWikiPageFactory()->newFromTitle($new),$admin);
$status=$delete->deleteIfAllowed('isolated deletion test');
if (!$status->isOK()) {throw new RuntimeException('Delete failed');}
MediaWiki\Deferred\DeferredUpdates::doUpdates();
checkTest(!$new->exists(Wikimedia\Rdbms\IDBAccessObject::READ_LATEST),'real page deletion permitted');
$remaining=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($new));
checkTest(!isset($remaining->getProperties()['Name']),'real deletion clears derived SMW assertions');
echo "MOVE AND DELETE CASES PASSED\n";
