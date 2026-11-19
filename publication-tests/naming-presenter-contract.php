<?php
require __DIR__.'/bootstrap.php'; require_once __DIR__.'/message-fixture.php';
use AEWI\SWS\Presentation\{RestMoveSubjectPresenter,RestCreateSubjectPresenter,RestSetMainSubjectPresenter,RestReplaceSubjectPresenter};
function namingCheck($ok,$why){if(!$ok)throw new RuntimeException($why);}
$GLOBALS['swsTestUiLanguage']='de';
$move=new RestMoveSubjectPresenter();$move->presentSubjectNotFound();
namingCheck($move->getStatusCode()===404&&$move->getJsonArray()['message']==='Entität nicht gefunden','Localized text preserves HTTP status');
$GLOBALS['swsTestMessages']['de']['sws-api-entity-already-on-target']='Einheit bereits am Ziel';
$move->presentSubjectAlreadyOnTargetPage();namingCheck($move->getStatusCode()===409&&$move->getJsonArray()['message']==='Einheit bereits am Ziel','Application nomenclature override reaches API response');
$move->presentMoveIncomplete();namingCheck($move->getStatusCode()===500&&str_contains($move->getJsonArray()['message'],'zurückgesetzt'),'Incomplete move remains a distinct failure');
$create=new RestCreateSubjectPresenter();$create->presentSubjectAlreadyExists();namingCheck($create->getStatusCode()===409&&$create->getJsonArray()['message']==='Entität bereits vorhanden','Duplicate entity remains 409');
$set=new RestSetMainSubjectPresenter();$set->presentSubjectNotFound();namingCheck($set->getStatusCode()===404,'Missing main entity remains 404');
$replace=new RestReplaceSubjectPresenter();$replace->presentSaveFailed('Grund');namingCheck($replace->getStatusCode()===500&&str_ends_with($replace->getJsonArray()['message'],': Grund'),'Save failure retains diagnostic detail');
echo "Entity API nomenclature overrides, status preservation and diagnostics passed.\n";
