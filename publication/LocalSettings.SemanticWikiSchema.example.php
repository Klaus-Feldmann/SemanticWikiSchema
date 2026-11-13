<?php
/** First-installation example. Merge into LocalSettings.php; do not replace it. */
$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;
wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension( 'SchemaBaseBridge', "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json" );
$wgSWSAutoRenderMainSubject = false;
// Source mode defaults to store; PrefixSearch defaults to enabled with all namespaces.
// $wgSWSSourceDataMode = 'cache';
// $wgSWSPrefixSearchNamespaces = [ NS_MAIN, NS_CATEGORY, NS_TEMPLATE ];
// $wgSWSAdditionalSubjectNamespaces = [ 828, NS_TEMPLATE, NS_MEDIAWIKI ];
// Optional separate Fuseki store; configure the service before uncommenting.
// $wgSWSSparqlStores = [ [
//     'name' => 'sws',
//     'updateUrl' => 'http://127.0.0.1:3331/sws/update',
//     'queryUrl' => 'http://127.0.0.1:3331/sws/query',
//     'projection' => 'native',
// ] ];
