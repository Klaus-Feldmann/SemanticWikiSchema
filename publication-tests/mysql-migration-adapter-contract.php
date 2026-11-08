<?php
require_once dirname(__DIR__).'/migration/IdentifierMigration.php';
require_once dirname(__DIR__).'/migration/MediaWikiDatabase.php';
use SWSMigration\MediaWikiDatabase;
final class RecordingMysql {
    public array $sql=[];
    public string $version='10.11.11-MariaDB';
    public function getType(){return'mysql';}
    public function getDomainID(){return'fixture-db-mw_';}
    public function getServerVersion(){return$this->version;}
    public function addIdentifierQuotes($v){return'`'.$v.'`';}
    public function tableName($v){return'`mw_'.$v.'`';}
    public function addQuotes($v){return"'".str_replace("'","''",$v)."'";}
    public function tableExists($name,$caller){return in_array($name,['neowiki_relation','slot_roles'],true);}
    public function commit($caller){}
    public function query($sql,$caller){
        $this->sql[]=$sql;
        if(str_starts_with($sql,'SHOW FULL COLUMNS'))return[(object)['Field'=>'nwr_page_id','Type'=>'int unsigned','Null'=>'NO','Default'=>null,'Extra'=>'','Collation'=>null,'Comment'=>'']];
        if(str_starts_with($sql,'SHOW INDEX'))return[(object)['Key_name'=>'nwr_target_id','Non_unique'=>1,'Seq_in_index'=>1,'Column_name'=>'swsr_page_id']];
        if(str_starts_with($sql,'SELECT'))return[(object)['role_id'=>7,'role_name'=>'neo']];
        return[];
    }
}
$db=new RecordingMysql();$adapter=new MediaWikiDatabase($db);$checks=0;
function mysqlCheck($pass){global$checks;$checks++;if(!$pass)throw new RuntimeException('MySQL adapter assertion failed');}
// Source table check must distinguish source and destination even under a table prefix.
$adapter->apply(['kind'=>'table','from'=>'neowiki_relation','to'=>'sws_relation']);
mysqlCheck(in_array('RENAME TABLE `mw_neowiki_relation` TO `mw_sws_relation`',$db->sql,true));
$adapter->apply(['kind'=>'column','table'=>'sws_relation','from'=>'nwr_page_id','to'=>'swsr_page_id']);
mysqlCheck(in_array('ALTER TABLE `mw_sws_relation` RENAME COLUMN `nwr_page_id` TO `swsr_page_id`',$db->sql,true));
$adapter->apply(['kind'=>'index','table'=>'sws_relation','from'=>'nwr_target_id','to'=>'swsr_target_id']);
mysqlCheck(in_array('ALTER TABLE `mw_sws_relation` RENAME INDEX `nwr_target_id` TO `swsr_target_id`',$db->sql,true));
$adapter->apply(['kind'=>'value','table'=>'slot_roles','field'=>'role_name','from'=>'neo','to'=>'sws','where'=>['role_id'=>7]]);
mysqlCheck(in_array("UPDATE `mw_slot_roles` SET `role_name`='sws' WHERE `role_id`='7' AND `role_name`='neo'",$db->sql,true));
$db->version='5.5.5-10.11.11-MariaDB';$adapter->assertDdlSupported();mysqlCheck(true);
$db->version='8.0.40';$adapter->assertDdlSupported();mysqlCheck(true);
foreach(['5.7.44','10.4.32-MariaDB']as$version){$db->version=$version;try{$adapter->assertDdlSupported();throw new LogicException('Old server accepted');}catch(RuntimeException $e){mysqlCheck(true);}}
echo "$checks MySQL/MariaDB adapter contracts passed (recorded SQL, prefixed tables and server-version guards; no live MySQL server).\n";
