<?php
declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use ProfessionalWiki\SWS\Application\IdentityMigration\DurableCheckpoint;
$trace=[];$commit=function()use(&$trace){$trace[]='committed';return true;};$persist=function($state)use(&$trace){$trace[]='ack:'.$state['operation'];};
(new DurableCheckpoint($commit,$persist))->save(['operation'=>1]);
if($trace!==['committed','ack:1'])throw new RuntimeException('Acknowledgement preceded commit');
$trace=[];try{(new DurableCheckpoint(static fn()=>false,$persist))->save(['operation'=>2]);throw new LogicException('Failed commit accepted');}catch(RuntimeException $e){if($trace!==[])throw new RuntimeException('Failed commit was acknowledged');}
$trace=[];try{(new DurableCheckpoint($commit,static function(){throw new RuntimeException('Disk failure');}))->save(['operation'=>3]);}catch(RuntimeException $e){if($trace!==['committed'])throw new RuntimeException('Journal failure changed database ordering');}
echo "3 D6 durable database-before-journal/commit-failure/lost-acknowledgement checks passed (controlled commit boundary).\n";
