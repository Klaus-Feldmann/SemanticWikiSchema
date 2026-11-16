<?php
require __DIR__.'/source-refresh-processing-contract.php';
// Actual InternalPageProcessor path after full revision parse and schema-template processing.
resetD2(true); \D2Harness\State::$shortdesc='Eigene Seite';
$processor->refresh($title);
$final=$processor->prepare($title);
d2check($final->output->getPageProperty('sws-description')==='Eigene Seite','Worker persists SHORTDESC choice on its completed output');
d2check(in_array('Kurzbeschreibung=Eigene Seite',end(\D2Harness\State::$plans),true),'Worker sends the same active description to SMW');
\D2Harness\State::$shortdesc=null;
$next=$processor->prepare($title);
d2check($next->output->getPageProperty('sws-description')===null,'Fresh parse does not reuse removed SHORTDESC');
echo "Ranked-description actual worker projection checks passed.\n";
