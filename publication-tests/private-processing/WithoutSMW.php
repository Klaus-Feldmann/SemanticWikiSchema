<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
$s=MediaWikiServices::getInstance();$u=$s->getUserFactory()->newFromName('TestAdmin');
foreach (['Schema:NoSMWProcessing'=>['main'=>new SchemaContent(json_encode(['title'=>'NoSMWProcessing','propertyDefinitions'=>['Programm'=>['type'=>'wikitext','execute'=>true,'wikitext'=>'Privater Schemainhalt']]]))],
'NoSMWProcessing'=>['main'=>new WikitextContent('Privater Artikel'),'sws'=>new SubjectContent(json_encode(['mainSubject'=>'s123456789abcde','subjects'=>['s123456789abcde'=>['schema'=>'NoSMWProcessing','label'=>'Test','statements'=>[]]]]))]] as $name=>$slots) {
 $p=$s->getWikiPageFactory()->newFromTitle(Title::newFromText($name));$up=$p->newPageUpdater($u);foreach($slots as $slot=>$content){$up->setContent($slot,$content);}$up->saveRevision(MediaWiki\CommentStore\CommentStoreComment::newUnsavedComment('without SMW test'),0);DeferredUpdates::doUpdates();
}
$t=Title::newFromText('NoSMWProcessing');$p=new InternalPageProcessor();$r=$p->prepare($t);$p->refresh($t);DeferredUpdates::doUpdates();
if ($s->hasService('SMW.ObjectCache') || !str_contains($r->output->getContentHolderText(),'Privater Schemainhalt')) {throw new RuntimeException('without-SMW case failed');}
echo "PASS private schema processing without SMW loaded\n";
