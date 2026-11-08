# Optional schema-driven Semantic MediaWiki annotations

This local candidate uses Semantic WikiSchema with SchemaBaseBridge, Scribunto and optionally Semantic MediaWiki. No annotation template call is required in articles or schemas.

## Wiki pages

The files in `Wikiseiten/` provide `Modul:WikiSchema`, `Vorlage:WikiSchema` and `Vorlage:WikiSchema/URL-Zeile`. `WIKISEITEN.json` identifies the pages. The query module calls the existing PHP resolver directly through `mw.sws.collectFields`; no separate API or config wiki module is needed. The module now uses mw.sws; replace the previously installed query module when activating the renamed extension. Wikidata and Wikibase modules are unchanged.

Move the former main module and templates, preserving their histories, and replace their source with the supplied files. Keep temporary redirects for existing calls, then update active callers. Do not edit historical conversation transcripts. Verify individual queries, URL rows and saved automatic annotations on the actual wiki before removing obsolete API/config pages.

## Server configuration

No override is necessary. Schema field names become SMW property names. The old Werk exceptions are not supplied. Optional overrides in LocalSettings.php:

~~~php
$wgWikiSchemaSmwMapping = [
    'properties' => [ 'Person' => [ 'Schemafeld' => 'SMW-Eigenschaft' ] ],
    'urlEntries' => [ 'subject' => 'Subjektkennung' ],
];
~~~

Omitted URL keys retain these defaults: `url` -> Quellen-URL, `label` -> Quellentitel, `caption` -> Quellenbezeichnung, `captionPage` -> Quellenbezeichnungsseite, `field` -> WikiSchema-Feld, `subject` -> WikiSchema-Subjekt. All six property names remain configurable. Captioned URL entries retain their caption-based property selection and grouped metadata.

Changing server settings requires affected pages to be reprocessed. There is no automatic page dependency on LocalSettings.php. In contrast, subject/schema changes keep their existing refresh behavior. Existing SMW property types are not modified. Define WikiSchema-Feld and WikiSchema-Subjekt as Text properties in the actual localized property namespace when using the new defaults; adapt tables that query the old names. Old annotations are replaced on normal full-page processing, not by a database migration.

Main subjects and linked dependent subjects on the same page contribute supported scalar/date annotations. Images and executable Wikitext are not mirrored as scalar annotations; standalone secondary subjects do not contribute. Preserve the running job worker and the existing Q-ID registration workflow. True source failures retain the existing safeguards.

## Tests

The PHP mapping tests check defaults, partial overrides, schema mappings, invalid settings and the absence of abandoned Werk exceptions. Historical Lua tests remain reference fixtures; the current module is separately checked without a separate API module. These isolated checks do not replace live save/job/SMW validation.
