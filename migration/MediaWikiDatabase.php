<?php

declare(strict_types=1);
namespace SWSMigration;
use RuntimeException;

/** Uses the already configured MediaWiki connection; never reads credentials into exports. */
final class MediaWikiDatabase implements Database {
    public function __construct(private object $db) {
        if($db->getType()!=='mysql')throw new RuntimeException('The live migration adapter currently supports MySQL/MariaDB only.');
    }
    public function identity():string{return $this->db->getDomainID();}
    private function identifier(string $name):string {
        if(!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/D',$name))throw new RuntimeException('Invalid identifier');
        return $this->db->addIdentifierQuotes($name);
    }
    private function table(string $name):string{$this->identifier($name);return$this->db->tableName($name);}
    private function query(string $sql):array {
        $result=$this->db->query($sql,__METHOD__);$rows=[];foreach($result as$row)$rows[]=(array)$row;return$rows;
    }
    public function exists(string $table):bool{return$this->db->tableExists($table,__METHOD__);}
    public function columns(string $table):array {
        $result=[];foreach($this->query('SHOW FULL COLUMNS FROM '.$this->table($table))as$row){
            $result[$row['Field']]=['type'=>$row['Type'],'null'=>$row['Null'],'default'=>$row['Default'],'extra'=>$row['Extra'],'collation'=>$row['Collation'],'comment'=>$row['Comment']];
        }return$result;
    }
    public function indexes(string $table):array {
        $result=[];foreach($this->query('SHOW INDEX FROM '.$this->table($table))as$row){
            $key=$row['Key_name'];$result[$key]??=['name'=>$key,'unique'=>(int)$row['Non_unique']===0,'columns'=>[]];$result[$key]['columns'][(int)$row['Seq_in_index']]=$row['Column_name'];
        }
        foreach($result as&$index){ksort($index['columns']);$index['columns']=array_values($index['columns']);}unset($index);return array_values($result);
    }
    public function rows(string $table,array $where=[]):array {
        $conditions=[];foreach($where as$key=>$value)$conditions[]=$this->identifier($key).'='.$this->db->addQuotes((string)$value);
        return$this->query('SELECT * FROM '.$this->table($table).($conditions?' WHERE '.implode(' AND ',$conditions):''));
    }
    public function assertDdlSupported():void {
        $version=(string)$this->db->getServerVersion();
        preg_match('/(?:5\.5\.5-)?([0-9]+\.[0-9]+\.[0-9]+)/',$version,$m);
        $minimum=stripos($version,'mariadb')!==false?'10.5.3':'8.0.0';
        if(!isset($m[1])||version_compare($m[1],$minimum,'<'))throw new RuntimeException('Column/index rename requires a checked MySQL >=8.0 or MariaDB >=10.5.3 server. Found '.$version);
    }
    public function apply(array $op):void {
        $this->assertDdlSupported();
        switch($op['kind']) {
            case 'table':
                if(!$this->exists($op['from'])||$this->exists($op['to']))throw new RuntimeException('Unexpected table state');
                $sql='RENAME TABLE '.$this->table($op['from']).' TO '.$this->table($op['to']);break;
            case 'column':
                $columns=$this->columns($op['table']);
                if(!isset($columns[$op['from']])||isset($columns[$op['to']]))throw new RuntimeException('Unexpected column state');
                $sql='ALTER TABLE '.$this->table($op['table']).' RENAME COLUMN '.$this->identifier($op['from']).' TO '.$this->identifier($op['to']);break;
            case 'index':
                $names=array_column($this->indexes($op['table']),'name');
                if(!in_array($op['from'],$names,true)||in_array($op['to'],$names,true))throw new RuntimeException('Unexpected index state');
                $sql='ALTER TABLE '.$this->table($op['table']).' RENAME INDEX '.$this->identifier($op['from']).' TO '.$this->identifier($op['to']);break;
            case 'value':
                $where=$op['where'];$where[$op['field']]=$op['from'];
                if($this->rows($op['table'],$where)===[])throw new RuntimeException('Expected source metadata missing');
                $conditions=[];foreach($where as$key=>$value)$conditions[]=$this->identifier($key).'='.$this->db->addQuotes((string)$value);
                $sql='UPDATE '.$this->table($op['table']).' SET '.$this->identifier($op['field']).'='.$this->db->addQuotes($op['to']).' WHERE '.implode(' AND ',$conditions);break;
            default:throw new RuntimeException('Unknown migration operation');
        }
        // MySQL DDL commits implicitly. No false claim of one atomic transaction across the migration.
        $this->db->commit(__METHOD__);
        $this->db->query($sql,__METHOD__);
        $this->db->commit(__METHOD__);
    }
}
