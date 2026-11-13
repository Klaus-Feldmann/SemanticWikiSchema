<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Persistence\MediaWiki\IdentityMigration;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Application\IdentityMigration\{MigrationHost,MigrationWriteScope,ReferenceRewriter,MigrationJsonShapeRepair,MigrationFieldRepairScope,MigrationFieldDefinitionSelector,MigrationFieldAdditionLog};
use ProfessionalWiki\SWS\Application\PageRefreshOutcome;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\{SchemaContent,SubjectContent,MappingContent,LayoutContent};
use ProfessionalWiki\SWS\EntryPoints\Processing\SourceRefreshReceipt;
use ProfessionalWiki\SWS\Persistence\MediaWiki\{PageContentSaver,PageContentSavingStatus,SubjectSlotPages};
use ProfessionalWiki\SWS\SWSExtension;
use Wikimedia\Rdbms\{IDatabase,IDBAccessObject};

/** Ordinary MediaWiki revisions and CAS writes; no direct revision/slot SQL manipulation. */
final class MediaWikiMigrationHost implements MigrationHost {
    private ?array $plan=null;
    private ?MigrationFieldAdditionLog $fieldLog=null;
    private ?\Closure $commitFields=null;
    private ?\Closure $progress=null;
    public function enableFieldRepair(MigrationFieldAdditionLog $log,\Closure $commit,\Closure $progress):void {
        $this->fieldLog=$log;$this->commitFields=$commit;$this->progress=$progress;
    }

    public function __construct(private Authority $actor,private \Closure $normalizeName) {}
    public function usePlan(array $plan):void { $this->plan=$plan; }
    private function services():MediaWikiServices { return MediaWikiServices::getInstance(); }
    private function saver():PageContentSaver { return new PageContentSaver($this->services()->getWikiPageFactory(),$this->actor); }
    private function revision(int $page):?RevisionRecord { return $this->services()->getRevisionLookup()->getRevisionByPageId($page,0,IDBAccessObject::READ_LATEST); }

    public function inventory():array {
        $services=$this->services();$extension=SWSExtension::getInstance();$db=$services->getConnectionProvider()->getPrimaryDatabase();
        foreach(['sws_identity','sws_id_counter','sws_identity_alias','sws_identity_host','sws_search_term']as$table) {
            if(!$db->tableExists($table,__METHOD__)) { throw new \RuntimeException('Required SWS table missing: '.$table); }
        }
        $counters=[];foreach($db->newSelectQueryBuilder()->select(['swsic_kind','swsic_value'])->from('sws_id_counter')->caller(__METHOD__)->fetchResultSet()as$row) { $counters[$row->swsic_kind]=(int)$row->swsic_value; }
        $occupied=array_map(static fn($title)=>str_replace('_',' ',$title),$db->selectFieldValues('page','page_title',['page_namespace'=>SWSExtension::NS_SCHEMA],__METHOD__));
        $records=[];$after=0;
        do {
            $condition=$db->makeList([
                $db->makeList(['page_namespace'=>[SWSExtension::NS_SCHEMA,SWSExtension::NS_LAYOUT,SWSExtension::NS_MAPPING]],IDatabase::LIST_AND),
                'page_id IN ('.SubjectSlotPages::newQuery($db)->getSQL().')',
            ],IDatabase::LIST_OR);
            $ids=$db->newSelectQueryBuilder()->select('page_id')->from('page')->where($condition)->andWhere($db->expr('page_id','>',$after))->orderBy('page_id')->limit(200)->caller(__METHOD__)->fetchFieldValues();
            foreach($ids as$id) {
                $after=(int)$id;$revision=$this->revision($after);$title=Title::newFromID($after);
                if($revision===null||$title===null) { throw new \RuntimeException('Page disappeared during inventory'); }
                $this->assertReadable($title,$revision);
                $slots=[];foreach($revision->getSlotRoles()as$role) { $content=$revision->getContent($role,RevisionRecord::FOR_THIS_USER,$this->actor);$slots[$role]=self::slotHash($content); }
                $base=['pageId'=>$after,'revisionId'=>$revision->getId(),'revisionSha1'=>$revision->getSha1(),'name'=>$title->getText(),'pageName'=>$title->getPrefixedText(),'slots'=>$slots];
                $main=$revision->getContent('main',RevisionRecord::FOR_THIS_USER,$this->actor);
                $kind=match(true){$main instanceof SchemaContent=>'schema',$main instanceof MappingContent=>'mapping',$main instanceof LayoutContent=>'layout',default=>null};
                if($kind!==null) { $records[]=$base+['kind'=>$kind,'slot'=>'main','json'=>$main->getText()]; }
                if($revision->hasSlot('sws')) {
                    $content=$revision->getContent('sws',RevisionRecord::FOR_THIS_USER,$this->actor);
                    if(!$content instanceof SubjectContent) { throw new \RuntimeException('Unreadable subject slot on '.$title->getPrefixedText()); }
                    // Force complete domain validation before proposing a write.
                    $content->getPageSubjects();
                    $records[]=array_replace($base,['kind'=>'subjects','slot'=>'sws','name'=>$title->getPrefixedText(),'json'=>$content->getText()]);
                }
            }
        }while(count($ids)===200);
        return ['wiki'=>$extension->getSubjectIdParser()->getLocalSourceKey(),'language'=>$services->getContentLanguage()->getCode(),
            'owner'=>$extension->getIdentityOwner($this->actor),'counters'=>$counters,'occupiedSchemaTitles'=>$occupied,'records'=>$records];
    }

    public function preflight(array $plan):void {
        $current=$this->inventory();
        if ($current['wiki']!==$plan['wiki'] || $current['owner']!==$plan['owner']) { throw new \RuntimeException('Plan belongs to another wiki or operator'); }
        $original=[];foreach($plan['inventory']['records']as$record) { $original[$record['pageId'].':'.$record['slot']]=$record; }
        $targets=[];$creates=[];$shapeRepairs=[];
        foreach($plan['operations']as$op) {
            if($op['action']==='create-schema') { $creates[$op['id']]=$op['json']; }
            else { $targets[$op['record']['pageId'].':'.$op['record']['slot']]=$op; }
        }
        foreach($current['records']as$record) {
            $key=$record['pageId'].':'.$record['slot'];
            if(!isset($original[$key])) {
                if($record['kind']==='schema'&&isset($creates[$record['name']])&&ReferenceRewriter::equivalent($record['json'],$creates[$record['name']])) { continue; }
                throw new \RuntimeException('Unplanned new data appeared since preview');
            }
            $old=$original[$key];
            if($record['revisionId']===$old['revisionId']&&$record['revisionSha1']===$old['revisionSha1']) { unset($original[$key]);continue; }
            $op=$targets[$key]??null;
            if($op===null||!self::samePayload($record['json'],$op['json'])) {
                if($op===null||$op['action']!=='update'||$record['kind']!=='subjects'||!MigrationJsonShapeRepair::matches($op['json'],$record['json'])) {
                    throw new \RuntimeException('Record changed since preview: '.$record['pageName'].' | '.$record['slot'].' | revisions '.$old['revisionId'].' / '.$record['revisionId']);
                }
                // Validate all records before doing any repair. Only an exact, signed first
                // migration child with the known empty-container conversion is eligible.
                $this->verifiedShapeRepairRevision($old,$record,$op['json']);
                $shapeRepairs[]=[$old,$record,$op['json']];
            }
            foreach($old['slots']as$role=>$hash) { if($role!==$old['slot']&&($record['slots'][$role]??null)!==$hash) { throw new \RuntimeException('Unrelated slot changed during migration'); } }
            unset($original[$key]);
        }
        foreach($original as$key=>$old) {
            $op=$targets[$key]??null;$revision=$this->revision($old['pageId']);$title=Title::newFromID($old['pageId']);
            if($op===null||$op['action']!=='retire-schema'||$revision===null||$title===null) { throw new \RuntimeException('Record disappeared since preview'); }
            $this->assertReadable($title,$revision);$content=$revision->getContent($old['slot'],RevisionRecord::FOR_THIS_USER,$this->actor);
            if(!$content instanceof WikitextContent||$content->getText()!==$op['json']) { throw new \RuntimeException('Retired schema differs from plan'); }
        }
        foreach($plan['operations']as$op) {
            if($op['action']==='create-schema') {
                $validator=\ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator::newInstance();
                if(!$validator->validate($op['json'])) { throw new \RuntimeException('Planned schema is invalid: '.json_encode($validator->getErrors())); }
            }
        }
        if($shapeRepairs!==[]) {
            MigrationWriteScope::run(function()use($shapeRepairs):void {
                foreach($shapeRepairs as[$old,$current,$expected]) { $this->repairJsonShape($old,$current,$expected); }
            });
        }
    }

    private function verifiedShapeRepairRevision(array $original,array $current,string $expected):RevisionRecord {
        $revision=$this->revision($current['pageId']);$title=Title::newFromID($current['pageId']);
        if($revision===null||$title===null||$revision->getId()!==$current['revisionId']||$revision->getSha1()!==$current['revisionSha1']) {
            throw new \RuntimeException('JSON shape repair base changed: '.$current['pageName']);
        }
        $this->assertReadable($title,$revision);
        $source=$this->services()->getRevisionLookup()->getRevisionById($original['revisionId']);
        if($revision->getParentId()!==$original['revisionId']||$source===null||$source->getSha1()!==$original['revisionSha1']||$source->getPageId()!==$revision->getPageId()) {
            throw new \RuntimeException('JSON shape repair is not the first migration child: '.$current['pageName']);
        }
        $roles=$revision->getSlotRoles();$expectedRoles=array_keys($original['slots']);sort($roles);sort($expectedRoles);
        if($roles!==$expectedRoles){throw new \RuntimeException('JSON shape repair slot set changed: '.$current['pageName']);}
        foreach($original['slots']as$role=>$hash){
            if($role!=='sws'&&self::slotHash($revision->getContent($role,RevisionRecord::FOR_THIS_USER,$this->actor))!==$hash){
                throw new \RuntimeException('JSON shape repair unrelated slot changed: '.$current['pageName']);
            }
        }
        $content=$revision->getContent('sws',RevisionRecord::FOR_THIS_USER,$this->actor);
        if(!$content instanceof SubjectContent||!MigrationJsonShapeRepair::matches($expected,$content->getText())||!SourceRefreshReceipt::isCurrent($title,$revision)) {
            throw new \RuntimeException('JSON shape repair has no matching signed source basis: '.$current['pageName']);
        }
        return $revision;
    }

    private function repairJsonShape(array $original,array $current,string $expected):void {
        $revision=$this->verifiedShapeRepairRevision($original,$current,$expected);$title=Title::newFromID($current['pageId']);
        $status=$this->saver()->updateContentAtRevision(new PageId($current['pageId']),$revision->getId(),function($parent)use($original,$current,$expected,$title){
            $this->verifiedShapeRepairRevision($original,$current,$expected);
            if($parent->getId()!==$current['revisionId']){throw new \RuntimeException('JSON shape repair CAS base changed');}
            $raw=json_decode($parent->getContent('sws',RevisionRecord::FOR_THIS_USER,$this->actor)->getText(),true,512,JSON_THROW_ON_ERROR);
            $next=SourceRefreshReceipt::attachMigrated($title,$parent,new SubjectContent($expected),$raw[SourceRefreshReceipt::KEY]['dependencies']);
            if(!self::samePayload($next->getText(),$expected)){throw new \LogicException('JSON shape repair did not preserve the exact migration payload');}
            return ['sws'=>$next];
        },CommentStoreComment::newUnsavedComment('SWS identity migration: repair empty JSON object representation'));
        if($status->status===PageContentSavingStatus::ERROR){throw new \RuntimeException($status->errorMessage??'JSON shape repair failed');}
    }

    private function assertReadable(Title $title,RevisionRecord $revision):void {
        $policy=SWSExtension::getInstance()->getRevisionPolicy();
        if(!$this->actor->authorizeRead('read',$title)||!$revision->userCan(RevisionRecord::DELETED_TEXT,$this->actor)||
            !$policy->revisionIsReadableBy($revision,$this->actor)||$policy->publishedRevision($revision)?->getId()!==$revision->getId()) {
            throw new \RuntimeException('Migration needs a readable, published current revision: '.$title->getPrefixedText());
        }
    }
    private static function slotHash($content):string {
        if($content===null) { throw new \RuntimeException('Unavailable content slot'); }
        return hash('sha256',$content->getModel()."\0".$content->serialize());
    }

    public function createSchema(string $id,string $json):void {
        $title=Title::makeTitleSafe(SWSExtension::NS_SCHEMA,$id);
        if($title===null||!$this->actor->authorizeRead('read',$title)||!$this->actor->authorizeWrite('edit',$title)||!$this->actor->isAllowed('createpage')||!$this->actor->isAllowed('sws-schema-edit')) { throw new \RuntimeException('Schema creation not authorized'); }
        if($title->exists()) {
            $revision=$this->revision($title->getArticleID());$this->assertReadable($title,$revision);
            $content=$revision->getContent('main',RevisionRecord::FOR_THIS_USER,$this->actor);
            if(!$content instanceof SchemaContent||!ReferenceRewriter::equivalent($content->getText(),$json)) { throw new \RuntimeException('Existing schema differs from planned result: '.$id); }
            return;
        }
        $status=$this->saver()->createPage($title,['main'=>new SchemaContent($json)],CommentStoreComment::newUnsavedComment('SWS identity migration: create '.$id));
        if($status->status===PageContentSavingStatus::ERROR) { throw new \RuntimeException($status->errorMessage??'Schema migration failed'); }
    }

    public function updateRecord(array $record,string $json,bool $redirect=false):void {
        $revision=$this->revision($record['pageId']);$title=Title::newFromID($record['pageId']);
        if($revision===null||$title===null) { throw new \RuntimeException('Migration page disappeared'); }
        $this->assertReadable($title,$revision);
        foreach($record['slots']as$role=>$hash) {
            if($role!==$record['slot']&&self::slotHash($revision->getContent($role,RevisionRecord::FOR_THIS_USER,$this->actor))!==$hash) { throw new \RuntimeException('Unrelated page content changed since preview'); }
        }
        $old=$revision->getContent($record['slot'],RevisionRecord::FOR_THIS_USER,$this->actor);
        $same=$redirect ? $old instanceof WikitextContent&&$old->getText()===$json : self::samePayload($old->getText(),$json);
        if($same) { return; } // Recover a committed write whose journal acknowledgement was lost.
        if($revision->getId()!==$record['revisionId']||$revision->getSha1()!==$record['revisionSha1']) { throw new \RuntimeException('Current revision changed since migration preview'); }
        if($redirect&&!$this->actor->isAllowed('editcontentmodel')) { throw new \RuntimeException('Retiring a schema requires content-model edit permission'); }
        $save=fn()=>$this->saver()->updateContentAtRevision(new PageId($record['pageId']),$revision->getId(),function($parent)use($record,$json,$redirect,$title){
            $content=match($record['kind']){'subjects'=>new SubjectContent($json),'mapping'=>new MappingContent($json),'layout'=>new LayoutContent($json),'schema'=>$redirect?new WikitextContent($json):new SchemaContent($json)};
            if($record['kind']==='subjects') {
                if($this->plan===null) { throw new \LogicException('Migration plan missing'); }
                $rewrite=new ReferenceRewriter($this->plan['schemas'],$this->plan['entities'],$this->plan['wiki'],$this->plan['language'],$this->normalizeName);
                $expected=$rewrite->subjects($parent->getContent('sws',RevisionRecord::FOR_THIS_USER,$this->actor)->getText(),$record['name']);
                if(!ReferenceRewriter::equivalent($expected,$json)) { throw new \RuntimeException('Entity rewrite differs from verified reference-only transformation'); }
                $content=$this->preserveReceipt($title,$parent,$content);
            }
            return [$record['slot']=>$content];
        },CommentStoreComment::newUnsavedComment('SWS identity migration: '.$record['kind']));
        $status=$redirect ? MigrationWriteScope::withSchemaRedirect($title->getDBkey(),$save) : $save();
        if($status->status===PageContentSavingStatus::ERROR) { throw new \RuntimeException($status->errorMessage??'Migration CAS write failed'); }
    }
    private static function samePayload(string $a,string $b):bool {
        $a=ReferenceRewriter::object($a);$b=ReferenceRewriter::object($b);unset($a->sourceRefresh,$b->sourceRefresh);
        return ReferenceRewriter::equivalent(ReferenceRewriter::encode($a),ReferenceRewriter::encode($b));
    }
    private function preserveReceipt(Title $title,RevisionRecord $parent,SubjectContent $next):SubjectContent {
        if(!SourceRefreshReceipt::isCurrent($title,$parent)) { return $next; }
        $raw=json_decode($parent->getContent('sws',RevisionRecord::FOR_THIS_USER,$this->actor)->getText(),true,512,JSON_THROW_ON_ERROR);
        $dependencies=$raw[SourceRefreshReceipt::KEY]['dependencies'];
        foreach($dependencies as&$dependency) {
            if($dependency['namespace']!==SWSExtension::NS_SCHEMA) { continue; }
            $name=($this->normalizeName)($dependency['title']);$id=$this->plan['schemas'][$name]??null;
            if($id===null) { throw new \RuntimeException('Unmapped schema dependency'); }
            $schemaTitle=Title::makeTitleSafe(SWSExtension::NS_SCHEMA,$id);$schema=$this->revision($schemaTitle->getArticleID());
            $this->assertReadable($schemaTitle,$schema);
            $dependency=['namespace'=>SWSExtension::NS_SCHEMA,'title'=>$schemaTitle->getDBkey(),'revision'=>$schema->getId()];
        }unset($dependency);
        return SourceRefreshReceipt::attachMigrated($title,$parent,$next,$dependencies);
    }
    private function migrationSchema(string $id):array {
        if($this->plan===null||!in_array($id,array_values($this->plan['schemas']),true)){throw new \RuntimeException('Schema is outside this migration plan: '.$id);}
        $title=Title::makeTitleSafe(SWSExtension::NS_SCHEMA,$id);$revision=$title===null?null:$this->revision($title->getArticleID());
        if($title===null||$revision===null){throw new \RuntimeException('Migration schema is missing: '.$id);}
        $this->assertReadable($title,$revision);$content=$revision->getContent('main',RevisionRecord::FOR_THIS_USER,$this->actor);
        if(!$content instanceof SchemaContent){throw new \RuntimeException('Migration schema content model changed: '.$id);}
        $data=ReferenceRewriter::object($content->getText());
        if(($data->formatVersion??null)!==2||($data->id??null)!==$id){throw new \RuntimeException('Migration schema identity mismatch: '.$id);}
        return [$title,$revision,$data,$content->getText()];
    }
    private function acknowledgeFieldCommit():void {
        if($this->commitFields===null||($this->commitFields)()===false){throw new \RuntimeException('Automatic field addition commit acknowledgement failed');}
    }
    private function recoverFieldAdditions():void {
        foreach($this->fieldLog->entries()as$key=>$entry){
            if($entry['status']==='completed'){continue;}
            [$title,$revision,$data,$json]=$this->migrationSchema($entry['schemaId']);
            if(ReferenceRewriter::equivalent($json,$entry['afterJson'])){
                $this->acknowledgeFieldCommit();$this->fieldLog->complete($key,$revision->getId());
                ($this->progress)('Recovered committed field addition: '.$entry['schemaId'].' / '.$entry['field']);
            }elseif($revision->getId()!==$entry['beforeRevision']||$revision->getSha1()!==$entry['beforeSha1']||!ReferenceRewriter::equivalent($json,$entry['beforeJson'])){
                throw new \RuntimeException('Pending automatic field addition conflicts with current schema: '.$entry['schemaId'].' / '.$entry['field']);
            }
        }
    }
    private function addMissingField(array $request):void {
        if($this->fieldLog===null){throw new \LogicException('Automatic fields require an explicit migration report');}
        if(count($this->fieldLog->entries())>=100){throw new \RuntimeException('Automatic field addition limit reached');}
        [$title,$revision,$data,$beforeJson]=$this->migrationSchema($request['schemaId']);
        if(isset($data->propertyDefinitions->{$request['field']})){throw new \RuntimeException('Requested field already exists; refusing retry loop: '.$request['field']);}
        $schemas=[];foreach(array_unique(array_values($this->plan['schemas']))as$id){$schemas[$id]=$this->migrationSchema($id)[2];}
        // A previous placeholder is not evidence for an inferred definition in another schema.
        // Recreate the fallback in the next schema's language and keep its review flag.
        foreach($this->fieldLog->entries()as$entry){
            if(($entry['needsReview']??false)&&$entry['field']===$request['field']){
                $candidate=$schemas[$entry['schemaId']]->propertyDefinitions->{$request['field']}??null;
                if($candidate!==null&&ReferenceRewriter::equivalent(ReferenceRewriter::encode($candidate),$entry['definitionJson'])){
                    unset($schemas[$entry['schemaId']]->propertyDefinitions->{$request['field']});
                }
            }
        }
        $language=str_starts_with($data->identityLanguage??$this->services()->getContentLanguage()->getCode(),'de')?'de':'en';
        $placeholder=wfMessage('sws-migration-field-added-placeholder')->inLanguage($language)->text();
        $selection=MigrationFieldDefinitionSelector::select($request['field'],$schemas,$placeholder);
        $next=ReferenceRewriter::object($beforeJson);$next->propertyDefinitions->{$request['field']}=$selection['definition'];$afterJson=ReferenceRewriter::encode($next);
        $validator=\ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator::newInstance();
        if(!$validator->validate($afterJson)){throw new \RuntimeException('Automatically completed schema is invalid: '.$request['schemaId']);}
        $entry=['schemaId'=>$request['schemaId'],'schemaLabel'=>$data->labels->{$data->identityLanguage??$language}??$request['schemaId'],
            'field'=>$request['field'],'page'=>$request['page'],'caller'=>$request['caller'],'language'=>$language,
            'selection'=>$selection['selection'],'sourceSchema'=>$selection['sourceSchema'],'needsReview'=>$selection['needsReview'],
            'matchingCandidates'=>$selection['matchingCandidates'],'candidates'=>$selection['candidates'],
            'definitionJson'=>ReferenceRewriter::encode($selection['definition']),'beforeRevision'=>$revision->getId(),'beforeSha1'=>$revision->getSha1(),
            'beforeJson'=>$beforeJson,'afterJson'=>$afterJson];
        $key=$this->fieldLog->prepare($entry);
        $status=$this->saver()->updateContentAtRevision(new PageId($title->getArticleID()),$revision->getId(),function($parent)use($revision,$afterJson){
            if($parent->getSha1()!==$revision->getSha1()){throw new \RuntimeException('Automatic schema field base changed');}
            return ['main'=>new SchemaContent($afterJson)];
        },CommentStoreComment::newUnsavedComment('SWS migration: add missing field '.$request['field']));
        if($status->status===PageContentSavingStatus::ERROR){throw new \RuntimeException($status->errorMessage??'Automatic schema field save failed');}
        $this->acknowledgeFieldCommit();
        [$title,$saved,$data,$savedJson]=$this->migrationSchema($request['schemaId']);
        if(!ReferenceRewriter::equivalent($savedJson,$afterJson)){throw new \RuntimeException('Automatic field addition result differs from planned definition');}
        $this->fieldLog->complete($key,$saved->getId());$title->invalidateCache();
        Title::newFromText($request['caller'])?->invalidateCache();SWSExtension::resetInstance();
        ($this->progress)('Field added: '.$entry['schemaId'].' ['.$entry['schemaLabel'].'] / '.$entry['field'].'; '.($entry['sourceSchema']===null?'text placeholder, review required':'copied from '.$entry['sourceSchema']));
    }

    public function rebuild():void {
        if($this->plan===null) { throw new \LogicException('Migration plan missing'); }
        $ids=[];foreach($this->plan['inventory']['records']as$record) { $ids[$record['pageId']]=true; }
        foreach($this->plan['schemas']as$id) { $title=Title::makeTitleSafe(SWSExtension::NS_SCHEMA,$id);if($title?->exists()) { $ids[$title->getArticleID()]=true; } }
        $rebuilder=new MediaWikiDataRebuilder();
        if($this->fieldLog===null) {
            foreach(array_keys($ids)as$id) { $rebuilder->rebuildPage((int)$id); }
            return;
        }
        $this->recoverFieldAdditions();
        for($round=0;$round<=100;$round++) {
            $restart=false;$position=0;
            foreach(array_keys($ids)as$id) {
                ($this->progress)('Rebuild '.(++$position).'/'.count($ids).': page '.$id.' ['.(Title::newFromID((int)$id)?->getPrefixedText()??'deleted').'] starting');
                $attempt=MigrationFieldRepairScope::attempt(fn()=>$rebuilder->rebuildPage((int)$id));
                if($attempt['error']===null){($this->progress)('Rebuild page '.$id.' complete');continue;}
                $matching=array_values(array_filter($attempt['missing'],static fn($request)=>str_contains($attempt['error']->getMessage(),'Field not found: '.$request['field'])));
                if($matching===[]){throw $attempt['error'];}
                if($round===100){throw new \RuntimeException('Automatic field addition retry limit reached');}
                foreach($matching as$request){$this->addMissingField($request);}
                $restart=true;break;
            }
            if(!$restart){return;}
            // A new definition can affect pages processed earlier in this pass too.
            ($this->progress)('Schema fields updated; restarting projection pass against current schemas');
        }
    }
}
