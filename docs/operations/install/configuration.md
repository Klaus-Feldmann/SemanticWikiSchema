# Configure LocalSettings.php

For the operator enabling the newly installed extension. Add this to the existing LocalSettings.php; do not replace the file:

```php
// SWS 0.4.03 is qualified with the legacy parser.
$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;

wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension(
    'SchemaBaseBridge',
    "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json"
);

// Metadata workflow: show entity data under Data rather than an automatic article infobox.
$wgSWSAutoRenderMainSubject = false;
```

SchemaBaseBridge is included in the package. Load SWS and the bridge once each. Load optional prerequisite extensions such as Scribunto, Wikibase and SMW according to their own instructions.

No setting is needed for these defaults:

- Source data mode: `store`.
- PrefixSearch enabled, with all namespaces offered.
- Page-first operation: `$wgSWSSubjectFirst = false`.

Optional overrides:

```php
$wgSWSPrefixSearchNamespaces = [ NS_MAIN, NS_CATEGORY, NS_TEMPLATE ];
$wgSWSAdditionalSubjectNamespaces = [ 828, NS_TEMPLATE, NS_MEDIAWIKI ];
// Alternative source-resolution mode:
// $wgSWSSourceDataMode = 'cache';
```

Additional namespaces supplement the content namespaces. For SMW annotations there, separately enable those namespace IDs in SMW's configuration. Namespace 0's selector label follows the wiki's `MediaWiki:Article` message.

PrefixSearch uses the installed search backend. Its namespace selector does not by itself provide arbitrary substring matching; advanced matching depends on the configured CirrusSearch capabilities and settings.

Leave RDF stores unconfigured for local data entry, or add the optional [Graph service](graph-explorer.md).

[Next: database, jobs and checks](verification.md)
