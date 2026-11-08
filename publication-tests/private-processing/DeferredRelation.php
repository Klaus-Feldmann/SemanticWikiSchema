<?php
// Isolated integration fixture only; Fixtures.php enforces the test database and server.
require __DIR__.'/Fixtures.php';
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
$services->getHookContainer()->register('ParserBeforeInternalParse', static function($parser, &$text, $stripState) {
    // Deterministic stand-in for Q discovery when the schema program enters the full parse.
    if ($parser->getTitle()->getText()==='Relation fixture' && str_contains($text,'identity-fixture')) {
        $parser->getOutput()->setPageProperty('unlinkedwikibase_id','Q188332');
    }
});
$childSchema=['title'=>'RelationChild','subjectsAreDependent'=>true,'propertyDefinitions'=>['Einordnung'=>['type'=>'text']]];
saveTestPage('Schema:RelationChild',new SchemaContent(json_encode($childSchema)));
$parentSchema=['title'=>'RelationParent','propertyDefinitions'=>[
    'Name'=>['type'=>'text'],
    'Forschung'=>['type'=>'relation','targetSchemas'=>['RelationChild'],'multiple'=>true],
    'Programm'=>['type'=>'wikitext','execute'=>true,'wikitext'=>'{{#tag:span|identity-fixture}}']
]];
saveTestPage('Schema:RelationParent',new SchemaContent(json_encode($parentSchema)));
$payload=['mainSubject'=>'sF1TCXCbyeFbSGK','subjects'=>[
    'sF1TCXCbyeFbSGK'=>['schema'=>'RelationParent','statements'=>[
        'Name'=>['type'=>'text','value'=>['Archelaos fixture']],
        'Forschung'=>['type'=>'relation','value'=>[['id'=>'rF1TCXCbyeFbSGN','target'=>'sF1TCXCbyeFbSGM']]]
    ]],
    'sF1TCXCbyeFbSGM'=>['schema'=>'RelationChild','statements'=>['Einordnung'=>['type'=>'text','value'=>['Nur im Kind']]]]
]];
$revision=saveTestPage('Relation fixture',new WikitextContent('Private fixture. [[Manuell::Bleibt]]'),new SubjectContent(json_encode($payload)));
$title=Title::newFromText('Relation fixture');
$job=new SchemaWikitextRefreshJob($title,['page'=>true]);
if (!$job->run()) { throw new RuntimeException('JOB: '.$job->getLastError()); }
checkTest(true,'actual refresh job accepts initial subject with dependent relation');
DeferredUpdates::doUpdates();
$qid=$services->getConnectionProvider()->getPrimaryDatabase()->newSelectQueryBuilder()->select('pp_value')->from('page_props')->where(['pp_page'=>$title->getArticleID(),'pp_propname'=>'unlinkedwikibase_id'])->caller(__FILE__)->fetchField();
checkTest($qid==='Q188332','identity from executable schema wikitext persisted');
$store=SMW\Services\ServicesFactory::getInstance()->getStore();
$data=$store->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
checkTest(isset($data->getProperties()['Name']),'supported parent annotation persisted');
checkTest(isset($data->getProperties()['Manuell']),'manual annotation preserved');
checkTest(!isset($data->getProperties()['Forschung']) && !isset($data->getProperties()['Einordnung']),'relation and child annotations deferred explicitly');
$actual=json_decode($services->getWikiPageFactory()->newFromTitle($title)->getRevisionRecord()->getContent('sws')->getText(),true);
checkTest($actual['subjects']===$payload['subjects'],'relation identity and dependent subject stored unchanged');
$payload['subjects']['sF1TCXCbyeFbSGK']['statements']['Name']['value']=['Geändert'];
saveTestPage('Relation fixture',new WikitextContent('Private fixture. [[Manuell::Bleibt]]'),new SubjectContent(json_encode($payload)));
checkTest((new SchemaWikitextRefreshJob($title,['page'=>true]))->run(),'subsequent subject update with relation succeeds');
DeferredUpdates::doUpdates();
$data=$store->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
$values=$data->getPropertyValues(new SMW\DIProperty('Name'));
checkTest(count($values)===1 && str_contains($values[0]->getSerialization(),'Geändert'),'updated scalar annotation persisted');
echo "RELATION_INTEGRATION_OK\n";
