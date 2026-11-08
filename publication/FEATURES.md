# Features and boundaries

The Data view keeps own, external and effective values separate. A populated own field wins; an empty own field may use the bound source. Qualified source IDs and URLs retain their identity even when display links lead to local pages.

Historical dates retain signed year, precision and calendar. Ambiguous source statements remain explicit; the user can supply a preferred research date. The display conventions currently focus on German and English. A rendered year link is not a calendar conversion or evidence that a disputed historical date is certain.

Image fields select local or Commons files, or derive references from Wikidata/local Wikibase properties. Data thumbnails are bounded; graph title thumbnails use the existing 50×50 slot. Exported images are file/repository references, not binary downloads.

Page fields use local title lookup. Structured URL values retain URL, display name, source caption and optional local caption target. Wikitext can be passive field content or a centrally executed schema program; only the latter runs as page-context configuration. Executable schema programs are not exported as research statements.

The optional automatic SMW integration maps supported schema fields to SMW properties. It preserves own-value precedence, deduplicates values, retains date calendars and uses subobjects for associated URL attributes. Images, relations and multilingual fields are not simplified into SMW facts. Existing SMW property types are not overwritten.

Saving relevant subjects reparses the hosting page; schema changes enqueue affected-page work. This is page/parser-based synchronization through the extension, not a direct replacement for SemanticWikibase or a guaranteed immediate refresh of externally changed data. A job runner and parser compatibility remain necessary.

The complete read interface exposes current main/additional subjects with definitions, ordered fields, status, values, descriptions, source identities and relationships. JSON preserves that model. Turtle and TriG include effective assertions, separately described own/source assertions and complete metadata. Raw subject editing APIs, graph-store projections and named stored-value mappings remain separate. A failed complete resolution returns a download error instead of a silently partial file.

The separately distributed MCP patch advertises a complete-view read tool when SchemaBaseBridge or its predecessor SWSBaseBridge is detected. It does not execute schema Wikitext, write SMW or change authentication. Its source/runtime baseline is MediaWiki-MCP 0.20.1; future versions require reconciliation.

The graph build is a guarded extension of captured Graph Explorer bundles, with exact embedded sources and a matching upstream source reference included. It is not presented as an independently refactored Graph Explorer source distribution. Neo4j/Docker demo compatibility and other parser/backend combinations have not been revalidated for this fork.

The extension registry and Composer installation name are SemanticWikiSchema. Existing sws APIs, config keys, data and custom hooks remain stable identifiers. Only the documented MW 1.47 legacy-parser deployment is supported. There is no supported Docker installation.

Schema presets initialize new subjects without overwriting existing values. URL presets may omit the address; incomplete optional presets are not persisted as source assertions. Individual Lua queries remain read-only, and legacy annotation calls are harmless.
