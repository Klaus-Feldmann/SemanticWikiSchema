<?php

declare(strict_types=1);
namespace D5Batch {
 final class State { public static array $queue=[], $keys=[], $queries=[]; public static int $deleted=37; }
 final class Query {
  private int $after=0;
  public function __call(string $name,array $args):self { State::$queries[]=[$name,$args];if($name==='andWhere'){$this->after=$args[0][2];}return $this; }
  public function fetchFieldValues():array{return array_slice(array_values(array_filter(range(1,255),fn($id)=>$id>$this->after)),0,100);}
 }
 final class Queue {
  public function push(object $job):void { $key=$job->title->getText().json_encode($job->params);if($job->removeDuplicates&&isset(State::$keys[$key]))return;State::$keys[$key]=true;State::$queue[]=$job; }
 }
}
namespace MediaWiki\Title {
 class Title {
  public function __construct(private string $text,public int $id=0){}
  public function getText():string{return $this->text;}
  public static function newFromID(int $id):?self{return $id===\D5Batch\State::$deleted?null:new self('Seite '.$id,$id);}
 }
}
namespace MediaWiki {
 class MediaWikiServices {
  public static function getInstance():self{return new self;}
  public function getJobQueueGroup():object{return new \D5Batch\Queue;}
  public function getConnectionProvider():object{return new class{public function getPrimaryDatabase():object{return new class{
   public function expr(mixed ...$args):array{return $args;}
   public function newSelectQueryBuilder():object{return new \D5Batch\Query;}
  };}};}
 }
}
namespace {
 class Job {
  public const JOB_NO_EXPLICIT_TRX_ROUND=1;
  public bool $removeDuplicates=false;public int $executionFlags=0;
  public function __construct(public string $type,public mixed $title,public array $params){}
 }
 require __DIR__.'/bootstrap.php';
 use ProfessionalWiki\SWS\EntryPoints\Jobs\SchemaWikitextRefreshJob;
 use D5Batch\State as S;
 $n=0;function batchCheck(bool $ok,string $why):void{global $n;$n++;if(!$ok)throw new RuntimeException($why);}
 $schema=new MediaWiki\Title\Title('Person');$first=new SchemaWikitextRefreshJob($schema,['after'=>0]);
 batchCheck($first->removeDuplicates&&($first->executionFlags&Job::JOB_NO_EXPLICIT_TRX_ROUND)!==0,'Existing job deduplication/transaction flags retained');
 batchCheck($first->run(),'First schema batch completed');
 batchCheck(count(S::$queue)===100,'99 existing pages and one cursor job after first batch');
 $saved=S::$queue;$first->run();batchCheck(count(S::$queue)===count($saved),'Replayed first batch adds no duplicate jobs in deduplicating host queue');
 $resumed=new SchemaWikitextRefreshJob($schema,end($saved)->params);S::$queue=[];
 batchCheck($resumed->params===['after'=>100]&&$resumed->run(),'Resume uses existing saved cursor after interruption');
 $second=S::$queue;batchCheck(count($second)===101&&end($second)->params===['after'=>200],'Second bounded batch schedules next cursor');
 S::$queue=[];(new SchemaWikitextRefreshJob($schema,end($second)->params))->run();$third=S::$queue;
 batchCheck(count($third)===55&&count(array_filter($third,fn($j)=>isset($j->params['after'])))===0,'Final short batch terminates without more cursor jobs');
 $pages=array_values(array_filter([...$saved,...$second,...$third],fn($j)=>($j->params['page']??false)===true));$ids=array_map(fn($j)=>$j->title->id,$pages);
 batchCheck(count($ids)===254&&count(array_unique($ids))===254,'Every existing indexed page scheduled exactly once across resumed batches');
 batchCheck(!in_array(37,$ids,true)&&max($ids)===255,'Deleted page skipped; later indexed pages retained');
 batchCheck(count(array_filter(S::$queries,fn($q)=>$q[0]==='where'&&$q[1]=== [['swss_schema'=>'Person']]))===4,'Real job uses existing named schema index');
 batchCheck(count(array_filter(S::$queries,fn($q)=>$q[0]==='limit'&&$q[1]===[100]))===4,'Every query remains bounded to 100 indexed rows');
 echo "$n D5 schema batch/replay/resume checks passed (controlled database and deduplicating queue, no live database).\n";
}
