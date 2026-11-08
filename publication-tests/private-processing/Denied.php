<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

require __DIR__ . '/Fixtures.php';
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;
$services->getHookContainer()->register('getUserPermissionsErrors',static function($page,$user,$action,&$result){
 if($action==='read' && $page->getPrefixedText()==='Schema:ProcessingTest' && $user->getName()===ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIdentity::NAME){$result=['badaccess-group0'];return false;}
 return true;
});
try {(new InternalPageProcessor())->refresh($title);throw new RuntimeException('schema ACL was bypassed');}catch(ProcessingIncomplete $e){}
$data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
checkTest(isset($data->getProperties()['Name']),'schema ACL denial aborts processing and preserves stored data');
