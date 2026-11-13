<?php
declare(strict_types=1);
require __DIR__.'/identity-hosting-contract.php';
use ProfessionalWiki\SWS\Application\DataRebuild\DataRebuildCoordinator;
use ProfessionalWiki\SWS\Domain\Page\PageId;
$nRebuild=0;function rebuildCheck(bool $ok,string $why):void{global$nRebuild;$nRebuild++;if(!$ok)throw new RuntimeException($why);}
// A matching normal index row can still have a lost derived hosting row. Rebuild must repair it.
$db->delete('sws_identity_host',['swsih_id'=>'E1'],__FILE__);
$index->reconcileSubjectsOfPage(new PageId(3),['E1']);
rebuildCheck((int)$db->selectField('sws_identity_host','swsih_page',['swsih_id'=>'E1'],__FILE__)===3,'Rebuild repairs host invariant even when page index contents already match');
$issued=(int)$db->pdo->query('SELECT COUNT(*) FROM mw_sws_identity')->fetchColumn();
$all=[2,5,8,11];$done=[];$checkpoints=[];$fail=true;$maxRequested=0;
$read=function(int $after,int $limit)use($all,&$maxRequested):array{$maxRequested=max($maxRequested,$limit);return array_slice(array_values(array_filter($all,fn($id)=>$id>$after)),0,$limit);};
$rebuild=function(int $id,bool $refresh)use(&$done,&$fail):void{if($id===8&&$fail){$fail=false;throw new RuntimeException('Temporary graph failure');}$done[]=[$id,$refresh];};
$coordinator=new DataRebuildCoordinator($read,$rebuild);
try{$coordinator->run(0,2,false,function($state)use(&$checkpoints){$checkpoints[]=$state;});throw new LogicException('Failure ignored');}catch(RuntimeException $e){rebuildCheck($e->getMessage()==='Temporary graph failure','Projection failure is reported rather than acknowledged');}
rebuildCheck(end($checkpoints)['after']===5,'Resume cursor stays before the failed page');
$result=$coordinator->run(5,2,false,function($state)use(&$checkpoints){$checkpoints[]=$state;});
rebuildCheck(array_column($done,0)===$all&&$result['after']===11&&$maxRequested===2,'Bounded resumed walk reaches every current page once');
rebuildCheck(array_column($done,1)===[false,false,false,false],'Default rebuild does not request forced source collection');
$coordinator->run(8,2,true,static function($state):void{});rebuildCheck(end($done)===[11,true],'Explicit source-refresh option reaches processing independently');
rebuildCheck((int)$db->pdo->query('SELECT COUNT(*) FROM mw_sws_identity')->fetchColumn()===$issued,'Repair never creates a new identity');
try{(new DataRebuildCoordinator(fn()=>[2,2],static function(){}))->run(0,2,false,static function(){});throw new LogicException('Invalid cursor accepted');}catch(RuntimeException){rebuildCheck(true,'Nonadvancing cursors are refused');}
echo "$nRebuild D6 rebuildData/cursor/repair/failure/source-option checks passed (actual coordinator and SQLite hosting index; controlled page processing).\n";
