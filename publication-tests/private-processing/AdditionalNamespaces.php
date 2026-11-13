<?php
// DESTRUCTIVE TEST FIXTURE: only the explicitly isolated test installation is admitted.
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\{WikitextContent,JsonContent};
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\EntryPoints\Actions\SubjectsAction;

$services=MediaWikiServices::getInstance();
if($services->getMainConfig()->get('DBname')!=='semantic_test' || $services->getMainConfig()->get('Server')!=='http://127.0.0.1:18747'){
    throw new RuntimeException('Only the isolated semantic_test installation may execute this fixture.');
}
if(!defined('NS_MODULE') || !$services->hasService('SMW.ParserDataFactory')){
    throw new RuntimeException('Fixture requires Scribunto and Semantic MediaWiki.');
}
foreach([NS_TEMPLATE,NS_MODULE,NS_MEDIAWIKI]as $ns){
    if(!in_array($ns,$services->getMainConfig()->get('SWSAdditionalSubjectNamespaces'),true)
        || !SMW\Services\ServicesFactory::getInstance()->getNamespaceExaminer()->isSemanticEnabled($ns)){
        throw new RuntimeException('Configure the additional SWS and SMW test namespaces before execution. No test page has been written.');
    }
}
$admin=$services->getUserFactory()->newFromName('TestAdmin');
function sws020check($condition,$message){if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
function sws020save($name,$content){
    global $services,$admin;
    $page=$services->getWikiPageFactory()->newFromTitle(Title::newFromText($name));
    $updater=$page->newPageUpdater($admin);$updater->setContent('main',$content);
    $revision=$updater->saveRevision(CommentStoreComment::newUnsavedComment('isolated SWS 0.2.0 fixture'),0);
    if(!$revision && !$updater->wasSuccessful())throw new RuntimeException($updater->getStatus()->getWikiText());
    DeferredUpdates::doUpdates();
}
function sws020subjects($id,$status,$category){
    return (new SubjectContent(json_encode(['mainSubject'=>$id,'subjects'=>[$id=>[
        'label'=>'Verwaltung','schema'=>'SWS020Verwaltung','statements'=>[
            'SWS020Status'=>['type'=>'text','value'=>$status===''?[]:[$status]],
            'Kategorien'=>['type'=>'page','value'=>$category?['Kategorie:SWS020 Metadaten']:[]]
        ]
    ]]],JSON_THROW_ON_ERROR)))->getPageSubjects();
}
sws020save('Schema:SWS020Verwaltung',new SchemaContent(json_encode(['title'=>'SWS020Verwaltung','propertyDefinitions'=>[
    'SWS020Status'=>['type'=>'text'],
    'Kategorien'=>['type'=>'page','multiple'=>true,'pageNamespace'=>14,'assignCategories'=>true]
]],JSON_THROW_ON_ERROR)));
$cases=[
    ['Vorlage:SWS020 Metadaten/Unterseite',new WikitextContent('Unveränderter Vorlagentext.'),'s22222222222222'],
    ['Modul:SWS020 Metadaten/Unterseite',new MediaWiki\Extension\Scribunto\ScribuntoContent('return { wert = 42 }'),'s33333333333333'],
    ['MediaWiki:SWS020-metadaten',new WikitextContent('Unveränderter Nachrichtentext.'),'s44444444444444'],
    ['MediaWiki:SWS020-metadaten/Unterseite.json',new JsonContent('{"wert":42}'),'s55555555555555']
];
$repository=SWSExtension::getInstance()->getSubjectRepository();
foreach($cases as [$name,$main,$id]){
    sws020save($name,$main);$title=Title::newFromText($name);$pageId=new PageId($title->getArticleID());
    sws020check(SubjectsAction::isEligibleTitle($title),'Data action available: '.$name);
    foreach([['geprüft',true],['geändert',false],['',false]]as [$status,$category]){
        $saved=$repository->savePageSubjects(sws020subjects($id,$status,$category),$pageId,'isolated SWS metadata save');
        sws020check($saved->status!=='error','Metadata write: '.$name);
        DeferredUpdates::doUpdates();
        $processor=new InternalPageProcessor;$result=$processor->prepare($title);
        sws020check(in_array('SWS020_Metadaten',$result->output->getCategoryNames(),true)===$category,'Derived category: '.$name);
        $processor->refresh($title);DeferredUpdates::doUpdates();
        $revision=$services->getRevisionLookup()->getRevisionByTitle($title);
        sws020check($revision->getContent('main',RevisionRecord::RAW)->serialize()===$main->serialize(),'Main content bytes preserved: '.$name);
        sws020check($revision->getSlot('main',RevisionRecord::RAW)->isInherited(),'Main slot inherited, not rewritten: '.$name);
        $view=$services->getRevisionRenderer()->getRenderedRevision($revision,ParserOptions::newFromUser($admin),$admin)->getRevisionParserOutput();
        sws020check(in_array('SWS020_Metadaten',$view->getCategoryNames(),true)===$category,'Ordinary view category: '.$name);
        $data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
        $property=$data->getProperties()['SWS020Status']??null;
        $values=$property?array_map(static fn($value)=>$value->getSerialization(),$data->getPropertyValues($property)):[];
        sws020check($status===''?$values===[]:in_array($status,$values,true),'Stored SMW annotation/change/removal: '.$name);
    }
}
echo "SWS 0.2.0 namespace/content-model integration fixture completed.\n";
