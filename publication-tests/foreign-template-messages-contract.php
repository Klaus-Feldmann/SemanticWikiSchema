<?php

declare(strict_types=1);
require __DIR__.'/source-refresh-processing-contract.php';

use D2Harness\State as ForeignState;
use ProfessionalWiki\SWS\EntryPoints\Processing\ProcessingIncomplete;
$n=0;function foreignCheck(bool $ok,string $why):void{global $n;$n++;if(!$ok)throw new RuntimeException($why);}
$messages=[file_get_contents(__DIR__.'/fixtures/foreign-literature-message.html'),
 '<span class="error">Unknown message from another extension</span>',
 '<span class="scribunto-error">Another template could not obtain its external value</span>'];
foreach(['cache','store'] as $mode){
 foreach($messages as $html){
  resetD2(true);ForeignState::$mode=$mode;ForeignState::$foreignHtml=$html;
  $processor->refresh($title);
  foreignCheck(count(ForeignState::$plans)===1&&count(ForeignState::$published)===1,'Foreign message permits annotation plan and secondary update: '.$mode);
  foreignCheck(ForeignState::$published[0]['known-revision-output']->getContentHolderText()===$html,'Foreign HTML message is preserved, not hidden: '.$mode);
 }
 resetD2(true);ForeignState::$mode=$mode;$processor->refresh($title);$before=count(ForeignState::$plans);editedD2();
 ForeignState::$swsFailure=true;ForeignState::$foreignHtml='<span class="error">Other message</span>';
 try{$processor->refresh($title);throw new LogicException('SWS failure accepted');}catch(ProcessingIncomplete $e){foreignCheck(str_contains($e->getMessage(),'SWS processing failed'),'Explicit own SWS failure remains a processing error: '.$mode);}
 foreignCheck(count(ForeignState::$plans)===$before,'Own failure does not publish partial new SMW data: '.$mode);
}
echo "$n foreign-message/SWS-owned-failure checks passed in cache and store (real worker, controlled MediaWiki/SMW host).\n";
