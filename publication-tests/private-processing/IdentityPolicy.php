<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

require __DIR__ . '/Fixtures.php';
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIdentity;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;
use ProfessionalWiki\SWS\SWSExtension;
$db=$services->getConnectionProvider()->getPrimaryDatabase();$id=ProcessingIdentity::get()->getId();
$before=$db->newSelectQueryBuilder()->select('*')->from('user')->where(['user_id'=>$id])->caller(__FILE__)->fetchRow();
$db->newUpdateQueryBuilder()->update('user')->set(['user_token'=>str_repeat('a',32)])->where(['user_id'=>$id])->caller(__FILE__)->execute();
try {try {ProcessingIdentity::get();throw new RuntimeException('human account taken over');}catch(RuntimeException $e) {checkTest(str_contains($e->getMessage(),'already in use'),'existing human account is not taken over');}}
finally {$db->newUpdateQueryBuilder()->update('user')->set(['user_token'=>$before->user_token])->where(['user_id'=>$id])->caller(__FILE__)->execute();}
$after=$db->newSelectQueryBuilder()->select('*')->from('user')->where(['user_id'=>$id])->caller(__FILE__)->fetchRow();
checkTest((array)$before===(array)$after,'collision test leaves account record unchanged');
SWSExtension::getInstance()->getRevisionPolicyRegistry()->setPolicy(new class implements ProfessionalWiki\SWS\Application\RevisionPolicy {
 public function publishedRevision(MediaWiki\Revision\RevisionRecord $revision): ?MediaWiki\Revision\RevisionRecord {return null;}
 public function revisionIsReadableBy(MediaWiki\Revision\RevisionRecord $revision,MediaWiki\Permissions\Authority $viewer): bool {return true;}
});
try {(new InternalPageProcessor())->prepare($title);throw new RuntimeException('unpublished revision processed');}catch(ProcessingIncomplete $e){checkTest($e->state==='pending','registered publication policy cannot be bypassed by processing user');}
echo "IDENTITY AND PUBLICATION CASES PASSED\n";
