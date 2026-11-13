<?php
// Writes only to the explicitly isolated installation. No Wikidata network is needed here.
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Parser\ParserOptions;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
$services=MediaWikiServices::getInstance();
if($services->getMainConfig()->get('DBname')!=='semantic_test'||$services->getMainConfig()->get('Server')!=='http://127.0.0.1:18747')throw new RuntimeException('Only the isolated semantic_test installation is admitted. No page written.');
$admin=$services->getUserFactory()->newFromName('TestAdmin');
function sws0202check($condition,$message){if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
function sws0202save($title,$content){global $services,$admin;$page=$services->getWikiPageFactory()->newFromTitle($title);$updater=$page->newPageUpdater($admin);$updater->setContent('main',$content);$revision=$updater->saveRevision(CommentStoreComment::newUnsavedComment('isolated SWS 0.2.02 fixture'),0);if(!$revision&&!$updater->wasSuccessful())throw new RuntimeException($updater->getStatus()->getWikiText());DeferredUpdates::doUpdates();}
$hasSmw=$services->hasService('SMW.ParserDataFactory');$suffix=wfMessage('sws-notes-property-suffix')->inContentLanguage()->text();
if($hasSmw){foreach(['SWS0202Ort'.$suffix,'SWS0202Eigen']as $name)sws0202save(Title::makeTitleSafe(SMW_NS_PROPERTY,$name),new WikitextContent('[[Has type::Text]]'));}
sws0202save(Title::newFromText('Schema:SWS0202Notizen'),new SchemaContent(json_encode(['propertyDefinitions'=>[
 'SWS0202Ort'=>['type'=>'text','fieldNotes'=>['type'=>'wikitext','annotate'=>true]],'SWS0202Eigen'=>['type'=>'text']
]],JSON_THROW_ON_ERROR)));
$title=Title::newFromText('SWS0202 Notiztest');$main='Unveränderter Hauptinhalt.';sws0202save($title,new WikitextContent($main));$id='s77777777777777';
foreach(['[[Brünn]] [https://example.org Beleg]','[[Brno]]','']as $note){
 $subjects=(new SubjectContent(json_encode(['mainSubject'=>$id,'subjects'=>[$id=>['schema'=>'SWS0202Notizen','fieldNotes'=>$note===''?[]:['SWS0202Ort'=>$note],'statements'=>['SWS0202Eigen'=>['propertyType'=>'text','value'=>['Eigene Forschung']]]]]],JSON_THROW_ON_ERROR)))->getPageSubjects();
 $status=SWSExtension::getInstance()->getSubjectRepository()->savePageSubjects($subjects,new PageId($title->getArticleID()),'isolated field note');sws0202check($status->status!=='error','Note saved');DeferredUpdates::doUpdates();
 $processor=new InternalPageProcessor;$processor->refresh($title);DeferredUpdates::doUpdates();
 $revision=$services->getRevisionLookup()->getRevisionByTitle($title);$stored=$revision->getContent('sws')->getPageSubjects()->getMainSubject();
 sws0202check(($stored->getFieldNotes()['SWS0202Ort']??'')===$note,'Author note roundtrip, including clearing');
 sws0202check($stored->getStatements()->getStatement(new ProfessionalWiki\SWS\Domain\Schema\PropertyName('SWS0202Ort'))===null,'Empty field has no artificial value');
 sws0202check($revision->getContent('main',RevisionRecord::RAW)->serialize()===$main,'Main content unchanged');
 $context=new MediaWiki\Context\DerivativeContext(MediaWiki\Context\RequestContext::getMain());$context->setUser($admin);$context->setRequest(new MediaWiki\Request\DerivativeRequest($context->getRequest(),['action'=>'swsrenderwikitext','pageid'=>$title->getArticleID(),'all'=>true,'format'=>'json']));
 $api=new MediaWiki\Api\ApiMain($context,false,true);$api->execute();$result=$api->getResult()->getResultData();$entry=$result['swsrenderwikitext']['entries'][json_encode([$id,'SWS0202Ort','note'])]??null;
 sws0202check($note===''?$entry===null:($entry['source']===$note&&str_contains($entry['html'],'href=')),'Real batched render response follows the saved note');
 if($hasSmw){$data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));$props=$data->getProperties();$key=str_replace(' ','_','SWS0202Ort'.$suffix);$p=$props[$key]??null;$values=$p?$data->getPropertyValues($p):[];sws0202check(($values!==[])===($note!==''),'SMW author note added, replaced and removed');}
}
echo "SWS 0.2.02 live field-note fixture completed. Source outages are covered separately by controlled source contracts.\n";
