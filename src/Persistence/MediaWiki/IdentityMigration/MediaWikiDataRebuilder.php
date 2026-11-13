<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\PageRefreshOutcome;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Processing\InternalPageProcessor;
use ProfessionalWiki\SWS\Persistence\MediaWiki\{DatabaseSubjectPageIndex,SubjectSlotPages};
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\SubjectSlotReader;
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\Rdbms\IDBAccessObject;

/** Rebuild current data, not historical revisions. Reuses the normal signed processor and publication policy. */
final class MediaWikiDataRebuilder {
    public function pageIdsAfter(int $after,int $limit):array {
        if($after<0||$limit<1||$limit>1000) { throw new \InvalidArgumentException('Invalid rebuild batch'); }
        $db=MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
        $namespaces=implode(',',[SWSExtension::NS_SCHEMA,SWSExtension::NS_LAYOUT,SWSExtension::NS_MAPPING]);
        $queries=['SELECT page_id AS p FROM '.$db->tableName('page').' WHERE page_namespace IN ('.$namespaces.')',
            'SELECT page_id AS p FROM ('.SubjectSlotPages::newQuery($db)->getSQL().') AS current_subject_pages'];
        foreach(['sws_subject'=>'swss_page_id','sws_subject_page'=>'swssp_page_id','sws_search_term'=>'swst_page_id','sws_identity_host'=>'swsih_page']as$table=>$column) {
            $queries[]='SELECT '.$column.' AS p FROM '.$db->tableName($table);
        }
        // Only validated integers and MediaWiki-quoted table names are interpolated.
        $sql='SELECT p FROM ('.implode(' UNION ',$queries).') AS candidates WHERE p>'.(int)$after.' ORDER BY p LIMIT '.(int)$limit;
        $ids=[];foreach($db->query($sql,__METHOD__)as$row) { $ids[]=(int)$row->p; }return $ids;
    }
    public function rebuildPage(int $pageId,bool $refreshSources=false):void {
        $services=MediaWikiServices::getInstance();$extension=SWSExtension::getInstance();
        $revision=$services->getRevisionLookup()->getRevisionByPageId($pageId,0,IDBAccessObject::READ_LATEST);
        if($revision===null) { $extension->withdrawDeletedDataPage($pageId);return; }
        $title=Title::newFromID($pageId);
        if($title===null) { throw new \RuntimeException('Current page title is missing'); }
        $reader=\ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIdentity::get();
        if(!$reader->authorizeRead('read',$title)||!$revision->userCan(\MediaWiki\Revision\RevisionRecord::DELETED_TEXT,$reader)||!$extension->getRevisionPolicy()->revisionIsReadableBy($revision,$reader)) {
            throw new \RuntimeException('Current revision is not readable by the data processor');
        }
        $content=SubjectSlotReader::read($revision);
        if($revision->hasSlot('sws') && $content===null) { throw new \RuntimeException('Unreadable current entity slot; no index rows changed'); }
        $ids=$content?->getSubjectIds()??[];
        foreach($ids as$id) { if(!preg_match('/^E[1-9][0-9]{0,17}\z/',$id)) { throw new \RuntimeException('Current legacy entity data requires identity migration before rebuildData: '.$pageId); } }
        (new DatabaseSubjectPageIndex($services->getConnectionProvider()->getPrimaryDatabase(),true))->reconcileSubjectsOfPage(new PageId($pageId),$ids);
        if(InternalPageProcessor::manages($title,$revision)) {
            if(!(new InternalPageProcessor())->refresh($title,null,$refreshSources,true)) { throw new \RuntimeException('SWS processing failed for '.$pageId); }
        } else {
            $outcome=$extension->newMigrationPageRebuilder()->rebuild($title);
            if(!in_array($outcome,[PageRefreshOutcome::Refreshed,PageRefreshOutcome::Unpublished],true)) { throw new \RuntimeException('Data projection incomplete: '.$pageId); }
        }
        $title->invalidateCache();
    }
}
