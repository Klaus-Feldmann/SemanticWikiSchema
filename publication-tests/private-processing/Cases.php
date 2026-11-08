<?php
// NEVER execute on a production wiki. These fixtures deliberately create/change/delete test pages.
if ( \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'DBname' ) !== 'semantic_test' ||
    \MediaWiki\MediaWikiServices::getInstance()->getMainConfig()->get( 'Server' ) !== 'http://127.0.0.1:18747' ) {
    throw new \RuntimeException( 'This test may run only in the isolated semantic_test installation.' );
}

require __DIR__ . '/Fixtures.php';
use MediaWiki\Title\Title;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingResult;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingContext;
use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
function storedProps($title) {return SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title))->getProperties();}
function dataValues($title,$property) {return array_map(fn($v)=>$v instanceof SMW\DataItems\WikiPage ? $v->getTitle()->getPrefixedText() : $v->getSerialization(),SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title))->getPropertyValues(SMW\DataItems\Property::newFromUserLabel($property)));}
$processor=new InternalPageProcessor();
$factory=$services->getService('SMW.ContentParserFactory');
checkTest($factory instanceof ProfessionalWiki\SWS\EntryPoints\Processing\SmwContentParserFactory,'SMW content parser factory decorated');
$parserOutput=$factory->newContentParser($title)->parse()->getOutput();
checkTest(ProcessingResult::isCurrent($title,$parserOutput->getExtensionData(ProcessingResult::STAMP)),'SMW content rebuild uses full authorized result');
$anon=$services->getUserFactory()->newAnonymous();
checkTest(!$anon->authorizeRead('read',$title),'anonymous read remains denied');
$canonical=$services->getRevisionRenderer()->getRenderedRevision($services->getRevisionLookup()->getRevisionByTitle($title),ParserOptions::newFromAnon())->getRevisionParserOutput();
checkTest($canonical->getExtensionData(ProcessingResult::STAMP)===null,'canonical render is never promoted to privileged output');
$before=dataValues($title,'Name');
$bad=new SMW\DataModel\SemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
try {SMW\Services\ServicesFactory::getInstance()->getStore()->updateData($bad);throw new RuntimeException('unguarded overwrite');}catch(ProcessingIncomplete $e){}
checkTest(dataValues($title,'Name')===$before,'direct incomplete SMW store write refused; old values intact');
// A real parser error must retain the previous complete state, including pageprops.
$oldResult=$processor->prepare($title);
$badSchema=$schema;$badSchema['propertyDefinitions']['Programm']['wikitext']='{{#invoke:MissingProcessingModule|bad}}';
saveTestPage('Schema:ProcessingTest',new SchemaContent(json_encode($badSchema)));
checkTest(!ProcessingResult::isCurrent($title,$oldResult->stamp),'schema change invalidates old result');
try {$processor->refresh($title);throw new RuntimeException('error output accepted');}catch(ProcessingIncomplete $e){}
checkTest(dataValues($title,'Name')===$before,'parser failure retains stored annotations');
checkTest(ProcessingContext::current()===null,'processing context restored after failure');
saveTestPage('Schema:ProcessingTest',new SchemaContent(json_encode($schema)));
// A normal core refresh and custom job do not nest their transaction rounds.
$job=new MediaWiki\JobQueue\Jobs\RefreshLinksJob($title,['recursive'=>false,'useRecursiveLinksUpdate'=>false]);
checkTest($job->run(),'regular refresh-links job accepted');DeferredUpdates::doUpdates();
checkTest(dataValues($title,'Name')===$before,'canonical refresh does not erase annotations');
$job=new SchemaWikitextRefreshJob($title,['page'=>true]);
checkTest($job->run(),'custom internal refresh job accepted');DeferredUpdates::doUpdates();
// Change an own value and then remove it, retaining manual article annotation.
$subject['subjects']['sProcessingTest']['statements']['Name']['value']=['Neuer Wert'];
saveTestPage('Processing fixture',new WikitextContent('Private article. [[Manuell::Erhalten]]'),new SubjectContent(json_encode($subject)));
$processor->refresh($title);DeferredUpdates::doUpdates();

checkTest(dataValues($title,'Name')===['Neuer Wert'],'changed own value replaces old annotation');
$subject['subjects']['sProcessingTest']['statements']=[];
saveTestPage('Processing fixture',new WikitextContent('Private article. [[Manuell::Erhalten]]'),new SubjectContent(json_encode($subject)));
$processor->refresh($title);DeferredUpdates::doUpdates();
checkTest(dataValues($title,'Name')===[],'intentional field clearing removes old annotation');
checkTest(isset(storedProps($title)['Manuell']),'clearing retains independent manual annotations');
// Empty all subjects and all main-slot annotations: complete-empty is a valid update.
saveTestPage('Processing fixture',new WikitextContent('Article without annotations.'),SubjectContent::newEmpty());
$processor->refresh($title);DeferredUpdates::doUpdates();
checkTest(dataValues($title,'Verarbeitungstest')===[] && dataValues($title,'Manuell')===[],'complete-empty clears obsolete annotations');
checkTest(isset(storedProps($title)['_SKEY']),'complete-empty keeps ordinary SMW metadata');
echo "ALL PRIVATE INTEGRATION CASES PASSED\n";
