# Semantic WikiSchema 0.5.02

- Default SMW property names use the field label in the wiki content language, falling back to the technical key, without an automatic schema-ID prefix. Notes follow the same naming. Explicit mappings retain priority.
- Page/schema SHORTDESC takes precedence over local Wikibase, then Wikidata. The first non-empty description supplies the search subtitle and automatic SMW `Kurzbeschreibung` annotation. Explicit schema fields may add independent values.
- Search reads the persisted description; typing does not fetch external descriptions. Main search and schema page fields share the lookup.
- English and German feature/configuration references cover all 22 registered settings, array suboptions, requirements and AI-assisted installation/schema design. Main pages remain concise.
- Existing processing diagnostics and the separately versioned MCP integration are retained.

## Installation

[English](docs/operations/install/README.md) · [Deutsch](docs/operations/install/de/README.md). The helper targets first installation. After replacing an existing extension installation, reprocess affected SWS pages so that SMW names and saved subtitles reflect current schemas. Keep jobs running. No new SQL structure, identity migration or search-index rebuild is required for these output changes.

Qualified core: MediaWiki 1.47.0-wmf.24, PHP 8.3.36, legacy parser, Vector 2022. [Verification](publication/VERIFICATION.md).

---

# Semantic WikiSchema 0.5.0

This release is based on the operator-supplied, installed `0.5.00-alpha.8` source. It adds the field-key and multilingual-label work to the published 0.4.03 line. The separately published MCP integration is retained unchanged.

- Every valid field key is scoped by its schema. `F1`, `N1`, `Z1` and descriptive keys are equally supported; numbering is manual and independent of field order.
- Field labels can be entered per language. Input forms, Data views, schema display and Graph Explorer choose the reader language with fallback; field keys and source P-ID bindings stay independent of labels.
- Changing a key in the schema editor records an explicit transfer. Existing page jobs transfer statements, relation identities, field notes and all stored source languages, with per-entity progress and refusal of collisions or stale edits. Article and template Wikitext is not rewritten.
- `{{#sws_value: E3 | F12 }}` reads a field of an entity; `{{#sws_value: F12 }}` reads the current page's main entity. Caller-written links and template arguments can use the escaped result. The obsolete `subject=` parameter is removed from this parser function.
- Default RDF predicates and SMW names are schema-scoped for all keys; explicit semantic mappings remain available. Existing explicit SMW mappings on previous keys remain usable after a recorded transfer. SMW property pages and their datatypes are not created automatically.
- MediaWiki 1.47 settings validation, edit-notice handling, keyboard/focus behavior, multilingual optional-field messages and entity/schema label headings are maintained and corrected.
- The optional HDTO mapping example binds explicitly chosen schema IDs and fields.
- First-installation ZIPs and the pinned Composer helper are provided. The package preserves actual registration timestamps, and new frontend datatype/label messages are registered.

## Installation and compatibility

This installer is for a first installation into an existing MediaWiki. Existing SWS/NeoWiki users should contact [Klaus Feldmann / AufEinWort](mailto:info@aufeinwort.info) before switching. Updates and migration are handled separately; the helper refuses an existing installation.

Qualified live context: MediaWiki 1.47.0-wmf.24, PHP 8.3, legacy parser and Vector 2022. This is not qualification of MediaWiki 1.47 stable or every supported SQL backend. Local tests and controlled Composer first installation are distinguished from full MediaWiki runtime tests in [verification](publication/VERIFICATION.md).

The [MCP 0.5.0 patch for MediaWiki MCP Server 0.21.0](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0) is a separate optional download, not part of the wiki installer execution.

[English installation](docs/operations/install/README.md) · [Deutsche Installation](docs/operations/install/de/README.md) · [Field keys and labels (DE)](docs/authoring/field-identifiers.md)
