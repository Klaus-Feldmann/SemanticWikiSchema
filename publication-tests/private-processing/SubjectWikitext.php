<?php
// Writes only to the explicitly admitted isolated MediaWiki fixture installation.
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use MediaWiki\Content\WikitextContent;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\{InternalPageProcessor,UserWikitextRenderer};

$services=MediaWikiServices::getInstance();
if($services->getMainConfig()->get('DBname')!=='semantic_test' || $services->getMainConfig()->get('Server')!=='http://127.0.0.1:18747'){
 throw new RuntimeException('Only the isolated semantic_test installation may execute this fixture. No page was written.');
}
$admin=$services->getUserFactory()->newFromName('TestAdmin');
function sws0201check($condition,$message){if(!$condition)throw new RuntimeException($message);echo 'PASS '.$message."\n";}
function sws0201save($name,$content){global $services,$admin;
 $page=$services->getWikiPageFactory()->newFromTitle(Title::newFromText($name));$updater=$page->newPageUpdater($admin);$updater->setContent('main',$content);
 $revision=$updater->saveRevision(CommentStoreComment::newUnsavedComment('isolated SWS 0.2.01 fixture'),0);
 if(!$revision&&!$updater->wasSuccessful())throw new RuntimeException($updater->getStatus()->getWikiText());DeferredUpdates::doUpdates();
}
$schemaName='SWS0201Wikitext';$id='s66666666666666';
sws0201save('Vorlage:SWS0201 Fixture',new WikitextContent('[[Kategorie:SWS0201 Vorlage]]<span>SWS0201 Template</span>'));
sws0201save('Schema:'.$schemaName,new SchemaContent(json_encode(['propertyDefinitions'=>[
 'Vorlage'=>['type'=>'wikitext','wikitext'=>'[[Kategorie:SWS0201 Schema]]'],
 'Anmerkung'=>['type'=>'wikitextValue','multiple'=>true],
 'Text'=>['type'=>'text']
]],JSON_THROW_ON_ERROR)));
$title=Title::newFromText('SWS0201 Wikitexttest');$mainText='Unveränderter Artikeltext.';
sws0201save($title->getPrefixedText(),new WikitextContent($mainText));
$source='[[Brünn]] [https://example.org Quelle] {{SWS0201 Fixture}} [[Kategorie:SWS0201 Feld]] [[SWS0201Beleg::ja]]';
foreach([[$source],['[[Brno]] [[Kategorie:SWS0201 Geändert]]'],[]]as $index=>$values){
 $subjects=(new SubjectContent(json_encode(['mainSubject'=>$id,'subjects'=>[$id=>['label'=>'Wikitexttest','schema'=>$schemaName,'statements'=>[
  'Anmerkung'=>['type'=>'wikitextValue','value'=>$values],
  'Text'=>['type'=>'text','value'=>['[[Kategorie:SWS0201 Text unberührt]]']],
  'Vorlage'=>['type'=>'wikitext','value'=>['[[Kategorie:SWS0201 Alter Subjektwert]]']]
 ]]]],JSON_THROW_ON_ERROR)))->getPageSubjects();
 $saved=SWSExtension::getInstance()->getSubjectRepository()->savePageSubjects($subjects,new PageId($title->getArticleID()),'isolated wikitext test');
 sws0201check($saved->status!=='error','Subject saved');DeferredUpdates::doUpdates();
 $processor=new InternalPageProcessor;$result=$processor->prepare($title);$categories=$result->output->getCategoryNames();
 sws0201check(in_array('SWS0201_Schema',$categories,true),'Central Template executes without execute flag');
 sws0201check(in_array('SWS0201_Feld',$categories,true)===($index===0),'Field category appears, changes and disappears');
 sws0201check(in_array('SWS0201_Geändert',$categories,true)===($index===1),'Changed field category');
 sws0201check(in_array('SWS0201_Vorlage',$categories,true)===($index===0),'Transcluded field template category');
 sws0201check(!in_array('SWS0201_Text_unberührt',$categories,true)&&!in_array('SWS0201_Alter_Subjektwert',$categories,true),'Text and dormant old values never execute');
 $processor->refresh($title);DeferredUpdates::doUpdates();$revision=$services->getRevisionLookup()->getRevisionByTitle($title);
 sws0201check($revision->getContent('main',RevisionRecord::RAW)->serialize()===$mainText,'Main content preserved');
 $fieldOutput=(new UserWikitextRenderer)->render($title,$values,$admin,$revision->getId());$html=$fieldOutput->getContentHolderText();
 if($index===0){sws0201check(str_contains($html,'href=')&&str_contains($html,'example.org')&&str_contains($html,'SWS0201 Template'),'Links and template rendered by real MediaWiki parser');}
 $view=$services->getRevisionRenderer()->getRenderedRevision($revision,ParserOptions::newFromUser($admin),$admin)->getRevisionParserOutput();
 sws0201check(in_array('SWS0201_Feld',$view->getCategoryNames(),true)===($index===0),'Ordinary view field metadata matches saved values');
 if($services->hasService('SMW.ParserDataFactory')){
  $data=SMW\Services\ServicesFactory::getInstance()->getStore()->getSemanticData(SMW\DataItems\WikiPage::newFromTitle($title));
  $property=$data->getProperties()['SWS0201Beleg']??null;$actual=$property?$data->getPropertyValues($property):[];
  sws0201check(($actual!==[])===($index===0),'Explicit SMW annotation added and removed');
 }
}
echo "SWS 0.2.01 real MediaWiki fixture completed.\n";
