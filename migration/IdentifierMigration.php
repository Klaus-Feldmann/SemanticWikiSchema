<?php

declare(strict_types=1);
namespace SWSMigration;

use RuntimeException;

interface Database {
    public function identity(): string;
    public function exists(string $table): bool;
    public function columns(string $table): array;
    public function indexes(string $table): array;
    public function rows(string $table, array $where=[]): array;
    public function apply(array $operation): void;
}

final class IdentifierMigration {
    public const TABLES = [
        'neowiki_relation'=>['target'=>'sws_relation','prefix'=>'nwr_','newPrefix'=>'swsr_'],
        'neowiki_subject'=>['target'=>'sws_subject','prefix'=>'nws_','newPrefix'=>'swss_'],
        'neowiki_subject_page'=>['target'=>'sws_subject_page','prefix'=>'nwsp_','newPrefix'=>'swssp_'],
        'neowiki_rebuild_runs'=>['target'=>'sws_rebuild_runs','prefix'=>'nwrr_','newPrefix'=>'swsrr_'],
    ];
    public const MODELS = ['NeoWikiSubject'=>'SWSSubject','NeoWikiSchema'=>'SWSSchema','NeoWikiLayout'=>'SWSLayout','NeoWikiMapping'=>'SWSMapping'];
    public const MARKERS = ['semantic-neowiki-internal-managed'=>'semantic-sws-internal-managed'];
    public function __construct(private Database $db) {}

    public function plan(): array {
        $ops=[]; $tables=[];
        foreach(self::TABLES as $old=>$map) {
            $new=$map['target'];
            if($this->db->exists($new)) { throw new RuntimeException('Target table already exists: '.$new.'. Refusing to merge or overwrite tables.'); }
            if(!$this->db->exists($old)) { continue; }
            $columns=$this->db->columns($old);
            foreach(array_keys($columns) as $column) {
                if(!str_starts_with($column,$map['prefix'])) { throw new RuntimeException('Unknown source column '.$old.'.'.$column); }
            }
            $ops[]=['kind'=>'table','from'=>$old,'to'=>$new];
            foreach(array_keys($columns) as $column) {
                $ops[]=['kind'=>'column','table'=>$new,'from'=>$column,'to'=>$map['newPrefix'].substr($column,strlen($map['prefix']))];
            }
            foreach($this->db->indexes($old) as $index) {
                if($index['name']==='PRIMARY') { continue; }
                if(!str_starts_with($index['name'],$map['prefix'])) { throw new RuntimeException('Unknown source index '.$old.'.'.$index['name']); }
                $ops[]=['kind'=>'index','table'=>$new,'from'=>$index['name'],'to'=>$map['newPrefix'].substr($index['name'],strlen($map['prefix']))];
            }
            $tables[$old]=count($this->db->rows($old));
        }
        foreach([['slot_roles','role_id','role_name',['neo'=>'sws']],['content_models','model_id','model_name',self::MODELS]] as [$table,$id,$field,$names]) {
            foreach($names as $old=>$new) {
                $from=$this->db->rows($table,[$field=>$old]);$to=$this->db->rows($table,[$field=>$new]);
                if($to!==[]) { throw new RuntimeException('Target '.$table.' name already exists: '.$new.'. No automatic ID merge.'); }
                if($from!==[]) {
                    if(count($from)!==1)throw new RuntimeException('Ambiguous source registry name');
                    $ops[]=['kind'=>'value','table'=>$table,'field'=>$field,'from'=>$old,'to'=>$new,'where'=>[$id=>$from[0][$id]]];
                }
            }
        }
        foreach(self::MODELS as $old=>$new) {
            if($this->db->rows('page',['page_content_model'=>$new])!==[])throw new RuntimeException('Target page content model already used: '.$new);
            if($this->db->rows('page',['page_content_model'=>$old])!==[])$ops[]=['kind'=>'value','table'=>'page','field'=>'page_content_model','from'=>$old,'to'=>$new,'where'=>[]];
        }
        if($this->db->exists('page_props'))foreach(self::MARKERS as $old=>$new) {
            if($this->db->rows('page_props',['pp_propname'=>$new])!==[])throw new RuntimeException('Target processing marker already exists');
            if($this->db->rows('page_props',['pp_propname'=>$old])!==[])$ops[]=['kind'=>'value','table'=>'page_props','field'=>'pp_propname','from'=>$old,'to'=>$new,'where'=>[]];
        }
        // Do not rename a shared slot role if any historical use belongs to another content model.
        $subjectModels=$this->db->rows('content_models',['model_name'=>'NeoWikiSubject']);
        $subjectModelId=$subjectModels[0]['model_id']??null;
        foreach($this->db->rows('slot_roles',['role_name'=>'neo'])as$role){
            foreach($this->db->rows('slots',['slot_role_id'=>$role['role_id']])as$slot){
                $content=$this->db->rows('content',['content_id'=>$slot['slot_content_id']]);
                if(count($content)!==1||$subjectModelId===null||(string)$content[0]['content_model']!==(string)$subjectModelId)throw new RuntimeException('A historical neo slot has an unexpected content model; inspect before migration.');
            }
        }
        // RevisionSlots::computeSha1 sorts primary slots by role name. Never change that order.
        $neoRevisions=[];
        foreach($this->db->rows('slot_roles',['role_name'=>'neo'])as$role)
            foreach($this->db->rows('slots',['slot_role_id'=>$role['role_id']])as$slot)$neoRevisions[(string)$slot['slot_revision_id']]=true;
        foreach($this->db->rows('slot_roles')as$role){
            $name=(string)$role['role_name'];
            if(strcmp($name,'neo')>0&&strcmp($name,'sws')<0)
                foreach($this->db->rows('slots',['slot_role_id'=>$role['role_id']])as$slot)
                    if(isset($neoRevisions[(string)$slot['slot_revision_id']]))throw new RuntimeException('Renaming neo to sws would change slot ordering in revision '.$slot['slot_revision_id'].'; inspect revision checksum handling before migration.');
        }
        return ['format'=>1,'affectedPageIds'=>$this->affectedPageIds(),'database'=>$this->db->identity(),'source'=>'NeoWiki/SWS pre-technical-rename identifiers','target'=>'SemanticWikiSchema 0.1.22','operations'=>$ops,'tables'=>$tables,'beforeHash'=>$this->fingerprint()];
    }

    public function affectedPageIds():array {
        $ids=[];
        foreach(self::TABLES as$old=>$map){
            $table=$this->db->exists($old)?$old:($this->db->exists($map['target'])?$map['target']:null);
            if($table===null)continue;
            foreach($this->db->rows($table)as$row)foreach([$map['prefix'].'page_id',$map['newPrefix'].'page_id']as$key)if(isset($row[$key]))$ids[(int)$row[$key]]=true;
        }
        foreach(self::MODELS as$old=>$new)foreach([$old,$new]as$name)foreach($this->db->rows('page',['page_content_model'=>$name])as$row)$ids[(int)$row['page_id']]=true;
        foreach(['neo','sws']as$role)foreach($this->db->rows('slot_roles',['role_name'=>$role])as$row){
            foreach($this->db->rows('slots',['slot_role_id'=>$row['role_id']])as$slot){
                foreach($this->db->rows('revision',['rev_id'=>$slot['slot_revision_id']])as$revision)$ids[(int)$revision['rev_page']]=true;
            }
        }
        $result=array_keys($ids);sort($result,SORT_NUMERIC);return$result;
    }

    /** Snapshot only extension indexes and the metadata/references whose identities are preserved. */
    public function snapshot(): array {
        $result=[];
        foreach(self::TABLES as $old=>$map) {
            $table=$this->db->exists($old)?$old:($this->db->exists($map['target'])?$map['target']:null);
            if($table===null)continue;
            $normalize=static fn(string $name): string=>str_starts_with($name,$map['newPrefix'])?$map['prefix'].substr($name,strlen($map['newPrefix'])):$name;
            $columns=[];foreach($this->db->columns($table) as $name=>$type)$columns[$normalize($name)]=$type;ksort($columns);
            $indexes=[];foreach($this->db->indexes($table) as $index){$index['name']=$normalize($index['name']);$index['columns']=array_map($normalize,$index['columns']);$indexes[$index['name']]=$index;}ksort($indexes);
            $rows=[];foreach($this->db->rows($table) as $row){$entry=[];foreach($row as $key=>$value)$entry[$normalize($key)]=$value;$rows[]=$entry;}
            $result[$old]=['columns'=>$columns,'indexes'=>$indexes,'rows'=>self::encodedRows($rows)];
        }
        $roles=[];foreach(['neo','sws']as$name)array_push($roles,...$this->db->rows('slot_roles',['role_name'=>$name]));
        $roleIds=[];foreach($roles as &$row){$roleIds[]=$row['role_id'];$row['role_name']='neo';}unset($row);
        $result['slot_roles']=self::encodedRows($roles);
        $slots=[];foreach($roleIds as$id)array_push($slots,...$this->db->rows('slots',['slot_role_id'=>$id]));
        $result['slots']=self::encodedRows($slots);
        $models=[];foreach(self::MODELS as$old=>$new)foreach([$old,$new]as$name)array_push($models,...$this->db->rows('content_models',['model_name'=>$name]));
        $modelIds=[];foreach($models as&$row){$modelIds[]=$row['model_id'];$row['model_name']=array_search($row['model_name'],self::MODELS,true)?:$row['model_name'];}unset($row);
        $result['content_models']=self::encodedRows($models);
        $content=[];foreach($modelIds as$id)array_push($content,...$this->db->rows('content',['content_model'=>$id]));
        $result['content']=self::encodedRows($content);
        $pages=[];foreach($this->affectedPageIds()as$id)foreach($this->db->rows('page',['page_id'=>$id])as$row){$row['page_content_model']=array_search($row['page_content_model'],self::MODELS,true)?:$row['page_content_model'];unset($row['page_touched'],$row['page_links_updated']);$pages[]=$row;}
        $result['page']=self::encodedRows($pages);
        if($this->db->exists('page_props')){
            $rows=[];foreach(self::MARKERS as$old=>$new)foreach([$old,$new]as$name)foreach($this->db->rows('page_props',['pp_propname'=>$name])as$row){$row['pp_propname']=$old;$rows[]=$row;}
            $result['page_props']=self::encodedRows($rows);
        }
        ksort($result);return$result;
    }
    private static function encodedRows(array $rows): array {
        $encoded=[];foreach($rows as$row){ksort($row);$out=[];foreach($row as$key=>$value)$out[$key]=$value===null?null:base64_encode((string)$value);$encoded[]=json_encode($out,JSON_THROW_ON_ERROR);}
        sort($encoded,SORT_STRING);return$encoded;
    }
    public function fingerprint():string{return hash('sha256',json_encode($this->snapshot(),JSON_THROW_ON_ERROR));}
    public static function inverse(array $op):array{[$op['from'],$op['to']]=[$op['to'],$op['from']];return$op;}

    public function apply(array $plan,string $journal):array {
        if($plan['database']!==$this->db->identity()||$plan['beforeHash']!==$this->fingerprint())throw new RuntimeException('Database state changed since report. No changes made.');
        $currentPlan=$this->plan();
        if($currentPlan['operations']!==$plan['operations']||$currentPlan['affectedPageIds']!==$plan['affectedPageIds'])throw new RuntimeException('Source identifiers or affected pages changed since report. No changes made.');
        if(file_exists($journal))throw new RuntimeException('Journal already exists.');
        $state=['format'=>1,'database'=>$this->db->identity(),'plan'=>$plan,'snapshot'=>$this->snapshot(),'completed'=>[],'pending'=>null,'state'=>'prepared'];
        self::save($journal,$state);
        try{
            foreach($plan['operations']as$index=>$op){
                $state['pending']=$index;self::save($journal,$state);
                $this->db->apply($op);
                $state['completed'][]=$index;$state['pending']=null;self::save($journal,$state);
            }
            if($this->fingerprint()!==$plan['beforeHash'])throw new RuntimeException('Post-migration data/ID fingerprint mismatch.');
            $state['state']='complete';self::save($journal,$state);return$state;
        }catch(\Throwable $e){
            // A DDL connection loss can leave a pending operation uncertain; do not guess or run destructive recovery.
            $state['state']='failed';$state['error']=$e->getMessage();self::save($journal,$state);
            throw new RuntimeException('Migration stopped; preserve journal and backups for inspected recovery. '.$e->getMessage(),0,$e);
        }
    }
    public function rollback(string $journal):void{
        $state=json_decode(file_get_contents($journal),true,512,JSON_THROW_ON_ERROR);
        if($state['database']!==$this->db->identity()||$state['state']!=='complete'||$state['pending']!==null)throw new RuntimeException('Only a completed migration of this database can be reverted automatically.');
        if($this->fingerprint()!==$state['plan']['beforeHash'])throw new RuntimeException('Affected data changed after migration; automatic rollback refused.');
        foreach(array_reverse($state['completed'])as$index){
            $state['pending']=$index;self::save($journal,$state);
            $this->db->apply(self::inverse($state['plan']['operations'][$index]));
            array_pop($state['completed']);$state['pending']=null;self::save($journal,$state);
        }
        if($this->fingerprint()!==$state['plan']['beforeHash'])throw new RuntimeException('Rollback fingerprint mismatch');
        $state['state']='rolled-back';self::save($journal,$state);
    }
    public static function save(string $path,array $data):void{
        $dir=dirname($path);if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot create backup directory');
        $tmp=tempnam($dir,'.sws-migration-');if($tmp===false)throw new RuntimeException('Cannot create journal');
        try{if(file_put_contents($tmp,json_encode($data,JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR)."\n")===false)throw new RuntimeException('Cannot write journal');chmod($tmp,0600);if(!rename($tmp,$path))throw new RuntimeException('Cannot finalize journal');}finally{if(file_exists($tmp))unlink($tmp);}
    }
}
