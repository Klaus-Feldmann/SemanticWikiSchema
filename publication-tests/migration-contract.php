<?php
/** Real SQLite storage exercises the migration engine; this is not a live MariaDB test. */
require_once dirname(__DIR__).'/migration/IdentifierMigration.php';
use SWSMigration\{Database,IdentifierMigration};

final class SqliteMigrationFixture implements Database {
    public PDO $pdo;
    public int $writes=0;
    public function __construct(private string $prefix=''){$this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);}
    public function identity():string{return 'sqlite-fixture-'.$this->prefix;}
    private function q(string $name):string {if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$name))throw new RuntimeException('Invalid fixture identifier');return'"'.$name.'"';}
    public function table(string $name):string{return$this->q($this->prefix.$name);}
    public function exists(string $table):bool{$s=$this->pdo->prepare('SELECT 1 FROM sqlite_master WHERE type=? AND name=?');$s->execute(['table',$this->prefix.$table]);return(bool)$s->fetchColumn();}
    public function columns(string $table):array{$out=[];foreach($this->pdo->query('PRAGMA table_info('.$this->table($table).')')as$row)$out[$row['name']]=['type'=>$row['type'],'null'=>$row['notnull'],'default'=>$row['dflt_value']];return$out;}
    public function indexes(string $table):array{
        $out=[];foreach($this->pdo->query('PRAGMA index_list('.$this->table($table).')')->fetchAll()as$row){
            $columns=array_column($this->pdo->query('PRAGMA index_info('.$this->q($row['name']).')')->fetchAll(),'name');
            $out[]=['name'=>$row['origin']==='pk'?'PRIMARY':$row['name'],'unique'=>(bool)$row['unique'],'columns'=>$columns];
        }return$out;
    }
    public function rows(string $table,array $where=[]):array{
        $conditions=[];foreach($where as$key=>$value)$conditions[]=$this->q($key).'=?';
        $q=$this->pdo->prepare('SELECT * FROM '.$this->table($table).($conditions?' WHERE '.implode(' AND ',$conditions):''));$q->execute(array_values($where));return$q->fetchAll();
    }
    public function apply(array $op):void{
        $this->writes++;
        switch($op['kind']){
            case'table':$this->pdo->exec('ALTER TABLE '.$this->table($op['from']).' RENAME TO '.$this->q($this->prefix.$op['to']));break;
            case'column':$this->pdo->exec('ALTER TABLE '.$this->table($op['table']).' RENAME COLUMN '.$this->q($op['from']).' TO '.$this->q($op['to']));break;
            case'index':
                $index=array_values(array_filter($this->indexes($op['table']),fn($i)=>$i['name']===$op['from']))[0];
                $this->pdo->exec('DROP INDEX '.$this->q($op['from']));
                $this->pdo->exec('CREATE '.($index['unique']?'UNIQUE ':'').'INDEX '.$this->q($op['to']).' ON '.$this->table($op['table']).'('.implode(',',array_map(fn($x)=>$this->q($x),$index['columns'])).')');break;
            case'value':
                $where=$op['where'];$where[$op['field']]=$op['from'];$conditions=[];foreach($where as$key=>$value)$conditions[]=$this->q($key).'=?';
                $q=$this->pdo->prepare('UPDATE '.$this->table($op['table']).' SET '.$this->q($op['field']).'=? WHERE '.implode(' AND ',$conditions));$q->execute(array_merge([$op['to']],array_values($where)));break;
            default:throw new RuntimeException('Unknown op');
        }
    }
    public function insert(string $table,array $data):void{$q=$this->pdo->prepare('INSERT INTO '.$this->table($table).'('.implode(',',array_map(fn($s)=>$this->q($s),array_keys($data))).') VALUES('.implode(',',array_fill(0,count($data),'?')).')');$q->execute(array_values($data));}
}
function fixture(string $prefix=''):SqliteMigrationFixture{
    $db=new SqliteMigrationFixture($prefix);
    foreach(IdentifierMigration::TABLES as$old=>$map){
        $sql=file_get_contents(dirname(__DIR__).'/sql/sqlite/'.$map['target'].'.sql');
        $sql=str_replace($map['newPrefix'],$map['prefix'],$sql);$sql=str_replace('/*_*/'.$map['target'],$prefix.$old,$sql);$db->pdo->exec($sql);
    }
    foreach([
        'slot_roles'=>'role_id INTEGER PRIMARY KEY,role_name TEXT UNIQUE',
        'content_models'=>'model_id INTEGER PRIMARY KEY,model_name TEXT UNIQUE',
        'page'=>'page_id INTEGER PRIMARY KEY,page_content_model TEXT,page_latest INTEGER,page_touched TEXT',
        'revision'=>'rev_id INTEGER PRIMARY KEY,rev_page INTEGER,rev_parent_id INTEGER',
        'slots'=>'slot_revision_id INTEGER,slot_role_id INTEGER,slot_content_id INTEGER,slot_origin INTEGER',
        'content'=>'content_id INTEGER PRIMARY KEY,content_model INTEGER,content_sha1 TEXT,content_address TEXT',
        'page_props'=>'pp_page INTEGER,pp_propname TEXT,pp_value BLOB',
        'text'=>'old_id INTEGER PRIMARY KEY,old_text BLOB',
        'archive'=>'ar_rev_id INTEGER PRIMARY KEY,ar_page_id INTEGER,ar_title TEXT',
    ]as$table=>$columns)$db->pdo->exec('CREATE TABLE '.$db->table($table).'('.$columns.')');
    $db->insert('slot_roles',['role_id'=>7,'role_name'=>'neo']);$db->insert('slot_roles',['role_id'=>1,'role_name'=>'main']);
    foreach(array_keys(IdentifierMigration::MODELS)as$i=>$name)$db->insert('content_models',['model_id'=>10+$i,'model_name'=>$name]);
    $db->insert('page',['page_id'=>1,'page_content_model'=>'wikitext','page_latest'=>22,'page_touched'=>'20261108000000']);
    $db->insert('page',['page_id'=>2,'page_content_model'=>'NeoWikiSchema','page_latest'=>23,'page_touched'=>'20261108000000']);
    foreach([21,22,24]as$rev){
        if($rev!==24)$db->insert('revision',['rev_id'=>$rev,'rev_page'=>1,'rev_parent_id'=>$rev-1]);
        $db->insert('slots',['slot_revision_id'=>$rev,'slot_role_id'=>7,'slot_content_id'=>$rev,'slot_origin'=>$rev]);
        $db->insert('content',['content_id'=>$rev,'content_model'=>10,'content_sha1'=>'sha-'.$rev,'content_address'=>'tt:'.$rev]);
        $db->insert('text',['old_id'=>$rev,'old_text'=>'{"mainSubject":"sExisting","subjects":{"sExisting":{"schema":"Person","statements":{"Notiz":{"propertyType":"text","value":"NeoWiki bleibt als Forschungsinhalt erhalten – \\u0000"}}}}}']);
    }
    $db->insert('revision',['rev_id'=>23,'rev_page'=>2,'rev_parent_id'=>0]);
    $db->insert('archive',['ar_rev_id'=>24,'ar_page_id'=>3,'ar_title'=>'Deleted person']);
    $db->insert('page_props',['pp_page'=>1,'pp_propname'=>'semantic-neowiki-internal-managed','pp_value'=>'1']);
    $db->insert('page_props',['pp_page'=>1,'pp_propname'=>'unlinkedwikibase_id','pp_value'=>'Q5']);
    $db->insert('neowiki_subject',['nws_page_id'=>1,'nws_subject_id'=>'sExisting','nws_schema'=>'Person','nws_display_name'=>"Existing\0Label",'nws_display_name_key'=>'existing','nws_page_key'=>'person','nws_is_main'=>1,'nws_dependent'=>0,'nws_edited'=>'20261108000000']);
    $db->insert('neowiki_subject_page',['nwsp_subject_id'=>'sExisting','nwsp_page_id'=>1]);
    $db->insert('neowiki_relation',['nwr_page_id'=>1,'nwr_subject_id'=>'sExisting','nwr_property'=>'NeoWiki als eigener Feldname','nwr_target_id'=>'sDependent']);
    $db->insert('neowiki_rebuild_runs',['nwrr_id'=>4,'nwrr_store'=>'aewi-neowiki','nwrr_status'=>'complete','nwrr_phase'=>'pages','nwrr_cursor'=>1,'nwrr_processed'=>1,'nwrr_failed'=>0,'nwrr_trigger'=>'manual','nwrr_started'=>'20261108000000','nwrr_finished'=>'20261108000001','nwrr_error'=>null]);
    return$db;
}
$checks=0;function check(bool $pass,string $message):void{global$checks;$checks++;if(!$pass)throw new RuntimeException($message);}
function refused(callable $action,string $message):void{try{$action();}catch(RuntimeException $e){check(true,$message);return;}check(false,$message);}
foreach(['','mw_']as$prefix){
    $db=fixture($prefix);$engine=new IdentifierMigration($db);$before=$engine->fingerprint();$blobs=$db->rows('text');$history=$db->rows('revision');$archive=$db->rows('archive');$plan=$engine->plan();
    check($db->writes===0,'Report does not mutate');check($engine->fingerprint()===$before,'Report leaves fingerprint unchanged');check($plan['affectedPageIds']===[1,2],'Targeted host/schema page list');
    $dir=sys_get_temp_dir().'/sws-migration-test-'.bin2hex(random_bytes(6));mkdir($dir,0700);$journal=$dir.'/VORGANG.json';
    $engine->apply($plan,$journal);check($engine->fingerprint()===$before,'Normalized state preserved');
    check($db->rows('slot_roles',['role_name'=>'sws'])[0]['role_id']===7,'Role numeric identity retained');
    check($db->rows('content_models',['model_name'=>'SWSSubject'])[0]['model_id']===10,'Content model numeric identity retained');
    check($db->rows('page',['page_id'=>2])[0]['page_content_model']==='SWSSchema','Main content model updated');
    check($db->rows('slots')[0]['slot_role_id']===7,'Historical slot reference unchanged');
    check($db->rows('text')===$blobs&&$db->rows('revision')===$history&&$db->rows('archive')===$archive,'Actual content blobs, revisions and deleted revision records unchanged');
    check($db->rows('sws_subject')[0]['swss_subject_id']==='sExisting','Subject identity retained');
    check($db->rows('sws_relation')[0]['swsr_property']==='NeoWiki als eigener Feldname','User data not globally rewritten');
    check($db->rows('sws_rebuild_runs')[0]['swsrr_store']==='aewi-neowiki','External store value retained');
    check($db->rows('page_props',['pp_propname'=>'unlinkedwikibase_id'])[0]['pp_value']==='Q5','Foreign extension data unchanged');
    check((fileperms($journal)&0777)===0600,'Private journal permissions');
    $db->pdo->exec('UPDATE '.$db->table('page')." SET page_touched='20261109000000'");
    check($engine->fingerprint()===$before,'Cache-only invalidation does not change the protected data fingerprint');
    foreach(IdentifierMigration::TABLES as$old=>$map){check(!$db->exists($old)&&$db->exists($map['target']),'Table names migrated');foreach(array_keys($db->columns($map['target']))as$column)check(str_starts_with($column,$map['newPrefix']),'Column prefix migrated');}
    refused(fn()=>$engine->apply($plan,$dir.'/again.json'),'Repeated/stale source application refused');
    $db->pdo->exec('UPDATE '.$db->table('sws_subject')." SET swss_display_name='changed'");
    refused(fn()=>$engine->rollback($journal),'Rollback refuses changed affected data');
    $q=$db->pdo->prepare('UPDATE '.$db->table('sws_subject').' SET swss_display_name=?');$q->execute(["Existing\0Label"]);
    $engine->rollback($journal);check($engine->fingerprint()===$before&&$db->exists('neowiki_subject'),'Exact data and names restored');
    check($db->rows('slot_roles',['role_name'=>'neo'])[0]['role_id']===7,'Role identity restored');unlink($journal);rmdir($dir);
}
$db=fixture();$engine=new IdentifierMigration($db);$plan=$engine->plan();$db->pdo->exec('UPDATE neowiki_subject SET nws_schema="Changed"');
refused(fn()=>$engine->apply($plan,sys_get_temp_dir().'/must-not-be-written.json'),'Changed data aborts before migration');check($db->writes===0,'No write on changed source');
$db=fixture();$db->pdo->exec('CREATE TABLE sws_subject (sentinel TEXT)');refused(fn()=>(new IdentifierMigration($db))->plan(),'Target table collision refused');check($db->writes===0,'Collision leaves data intact');
$db=fixture();$db->insert('slot_roles',['role_id'=>8,'role_name'=>'sws']);refused(fn()=>(new IdentifierMigration($db))->plan(),'Target slot collision refused');
$db=fixture();$db->insert('content_models',['model_id'=>20,'model_name'=>'SWSSubject']);refused(fn()=>(new IdentifierMigration($db))->plan(),'Target model collision refused');
$db=fixture();$db->pdo->exec('UPDATE content SET content_model=13 WHERE content_id=24');
refused(fn()=>(new IdentifierMigration($db))->plan(),'Unexpected model in a deleted historical slot is refused');
$db=fixture();$engine=new IdentifierMigration($db);$plan=$engine->plan();$plan['affectedPageIds']=[999999];
refused(fn()=>$engine->apply($plan,sys_get_temp_dir().'/must-not-be-written.json'),'Tampered affected-page list refused before writes');
check($db->writes===0,'No write for tampered report');
$db=fixture();$db->insert('slot_roles',['role_id'=>8,'role_name'=>'other']);$db->insert('slots',['slot_revision_id'=>24,'slot_role_id'=>8,'slot_content_id'=>21,'slot_origin'=>24]);
refused(fn()=>(new IdentifierMigration($db))->plan(),'Slot ordering change in deleted history refused');check($db->writes===0,'Slot ordering guard is read only');
$db=fixture();$db->insert('slot_roles',['role_id'=>8,'role_name'=>'other']);
check((new IdentifierMigration($db))->plan()['affectedPageIds']===[1,2],'Unused role does not block migration');
echo "$checks migration assertions passed against real SQLite fixtures (including prefixed tables, historical/deleted slots, binary values and rollback).\n";
