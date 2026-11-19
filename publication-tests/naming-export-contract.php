<?php
require __DIR__.'/export-rest-contract.php';
$GLOBALS['swsTestUiLanguage']='en';
$GLOBALS['swsTestMessages']['de']['sws-export-page-filename']='forschungsdaten-$1';
$r=$page->run(42);
check($r->headers['Content-Disposition']==='attachment; filename="forschungsdaten-42.json"','Actual download uses content-language filename override');
$GLOBALS['swsTestMessages']['de']['sws-export-page-filename']="unsafe\r\nInjected: $1";
$r=$page->run(42);
check($r->headers['Content-Disposition']==='attachment; filename="sws-page-42-data-view.json"','Invalid filename safely falls back without injecting a header');
$GLOBALS['swsTestMessages']['de']['sws-export-entity-filename']='einheit-$1';
$r=$subject->run('sF1FCQPNcwQdw4B');
check($r->headers['Content-Disposition']==='attachment; filename="einheit-sF1FCQPNcwQdw4B.json"','Entity filename keeps ID and caller-selected format');
echo "Actual export handlers: custom filenames, content language, entity identity and header safety passed.\n";
