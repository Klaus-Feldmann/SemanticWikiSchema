# SWS 0.5.02 — features and configuration

Reference for wiki operators, schema maintainers and authors. SWS stores structured entity data outside article text and provides schema-driven input through the **Data** tab.

## 1. Features and use

### Data model and editing

| Area | Capabilities |
|---|---|
| Schemas | Define typed fields, descriptions, required values, defaults and type-specific constraints; enter values through generated forms. |
| Entities | A main entity and additional entities on a page; an optional entity-first workflow creates standalone entity pages. |
| Relations | Link entities and edit dependent entities; model qualifiers and references with suitable schemas. No blanket import of every external reference structure is implied. |
| Identity | Stable S-IDs for schemas and E-IDs for entities with editable multilingual labels/descriptions. Relabeling a schema does not require moving its ID page. |
| Fields | Schema-local technical keys and separate multilingual labels. Keys may repeat in different schemas. No global field-ID registry or automatic field numbering. |
| Key changes | Schema-editor changes record transfers of stored values, notes and source snapshots through jobs. Conflicts are rejected. Authors must update their own templates and wikitext references. |
| Forms | Two-column, height-bounded forms with scrollable content; selectors sized for their labels. Qualified UI: Vector 2022, tablet/desktop. |
| Field notes | On-demand text/wikitext notes attached to a field, optionally annotated in SMW, including notes on fields without a value. |
| Validation | Type/schema checks, warning/error severities, editor feedback and server validation. Blocking new errors is configurable. |
| Edit notices | Page/schema-related notices before editing, independently of access controls. |
| Namespaces | Content namespaces plus configured additional namespaces, including template/module pages and non-wikitext content models. |
| Display | Data rows for populated values; multilingual field-label fallback; interface messages overridable through MediaWiki message pages. |

### Included field types

| Technical type | Use |
|---|---|
| `text` | Plain single/multiple text values; does not execute wikitext. |
| `wikitextValue` | User-entered entity wikitext, rendered in the result. |
| `wikitext` | Schema **Template**: centrally executed wikitext, not a normal entity input. |
| `page` | Wiki page with optional namespace; category links or actual category assignment. |
| `url` | External link with structured label/caption/caption-page metadata. |
| `image` | Image reference, for example local, Commons or a configured source. |
| `number` | Numeric values and type-specific bounds. |
| `boolean` | True/false. |
| `select` | Choose from defined options. |
| `relation` | Reference an entity of an allowed schema. |
| `date` | Calendar date. |
| `dateTime` | Date/time with explicit timezone. |
| `historicalDate` | Historical dates, including BCE and year/month precision, with calendar/source precision handling. |
| `monolingualText` | Language-tagged text. |

Multiplicity, defaults, constraints and link rendering are configured per field; options vary by type. A category-namespace page field assigns a category when category assignment is enabled; otherwise it is a page reference. A schema default can provide the category when no entity value is set.

### Sources, local data and annotations

- Configured Wikidata/local Wikibase bindings use entity associations and property IDs. A usable source response is required; SWS does not infer missing research facts.
- `store` persists processed source values locally; `cache` uses the cache/source resolution. Own entries take precedence. Source failures are handled per field without discarding unrelated manual input; last successful source states are retained according to the storage rules.
- Source status is separate from values. Field notes can include source notices and optional SMW annotations.
- Default SMW names use the field label in the wiki content language, falling back to the technical key. No automatic schema-ID prefix. Explicit mappings retain priority; separate schemas may intentionally share SMW properties.
- Description priority is **page/schema SHORTDESC → local Wikibase → Wikidata**. The first non-empty value supplies the search subtitle and an automatic SMW `Kurzbeschreibung` property. An explicit same-named schema field may add another value without changing the active search rank.
- Description selection is persisted during full page processing; typing a search does not fetch external descriptions. Search subtitles also work without SMW.

To promote an entity text field named `Kurzbeschreibung` to the highest description rank, add a separate schema Template field containing:

```wikitext
{{#if:{{#sws_value: Kurzbeschreibung}}|{{SHORTDESC:{{#sws_value: Kurzbeschreibung}}}}}}
```

The text field alone produces its usual annotation; SHORTDESC establishes search priority. Account for existing SHORTDESC declarations and their parse order when designing schemas.

### Search, output and interfaces

| Function | Use / prerequisite |
|---|---|
| PrefixSearch | Namespace selection, page suggestions, thumbnails, DISPLAYTITLE presentation and ranked subtitles; shared title lookup with schema page fields. |
| Matching | Uses the configured MediaWiki search backend; supplemental substring matching requires CirrusSearch regex support. Rendering DISPLAYTITLE does not itself index arbitrary display titles. |
| Direct values | `{{#sws_value: E3 \| F12 }}` reads the entity field; `{{#sws_value: F12 }}` uses the current main entity. Named fields work too. Neither `subject=` nor `entity=` is accepted by this parser function. |
| Custom output | Use values in caller-authored links/template arguments without executing arbitrary value text as wikitext. |
| Views/layouts | Explicit `#view` and `#main_subject` output, layouts and extensible view types; automatic main-entity rendering is configurable. |
| Creation | `#create_subject` exposes entity creation in permitted contexts. |
| Lua | `require('mw.sws')` reads values, entities, schemas and collections; backend-dependent `query`/`sparqlQuery`. The bundled bridge supplies source/data-view integration. |
| Graph queries | Read-only REST/Lua/`#sparql_raw`/`#cypher_raw` queries require the respective configured backend. Direct field reads do not. |
| JSON Schema export | Export a schema as a JSON Schema document for external validation. |
| JSON/REST | Structured reads, entity/schema management, validation and exports under existing access/revision rules. |
| RDF | Page/entity and bulk export, native identity IRIs and explicit ontology mappings; field predicates remain schema-scoped. |
| Graph Explorer | Linked entity/schema cards, labels, descriptions and images through a separately configured SPARQL endpoint/projection. |
| Graph stores | Neo4j/SPARQL projections, store status and controlled rebuilds. |
| Permissions | MediaWiki read/edit rights and publication policies remain in force. Namespace eligibility grants no rights by itself. |
| Maintenance | Jobs, RebuildData, graph/index rebuilds and RDF dumps. Changes propagate after their processing completes. |
| Extensibility | Field/view types, page-property providers, revision policies, edit notices, sources and graph backends. |
| MCP | Optional external MediaWiki MCP Server with permitted read/write tools, not a service automatically installed by SWS. |


### Management pages and namespaces

| Page / namespace | Purpose |
|---|---|
| `Special:Schemas` | List and edit schemas. |
| `Special:Entities` | Find entities. |
| `Special:Entity` | Open an entity by identifier. |
| `Special:CreateEntity` | Create an entity. |
| `Special:Layouts` / `Special:Mappings` | Manage layouts and ontology mappings. |
| `Special:GraphStores` | Manage graph stores and rebuilds. |
| `Special:SWS` | SWS overview; `MediaWiki:SWS` holds permitted wiki settings. |
| `Special:NeoJson` | Technical JSON development interface controlled by the development option. |
| `Schema` 7474, `Layout` 7476, `Mapping` 7478 | Management namespaces, each with a talk namespace. |
| `Entity` 7480 | Standalone entity pages in the corresponding workflow; talk namespace 7481. |

## 2. Complete LocalSettings.php reference

The table covers **21 SWS settings and one bundled SchemaBaseBridge setting**. Defaults apply without overrides. Examples are alternatives, not a mandatory block to paste wholesale. Reprocess affected pages/projections when needed; not every setting requires a complete rebuild.

| Variable | Default | Accepted form | Effect |
|---|---|---|---|
| `$wgSWSSourceDataMode` | 'store' | `'store'`, `'cache'` | Persist source snapshots in entities, or resolve through the existing cache. |
| `$wgWikiSchemaSmwMapping` | [] | Array; see suboptions below | Optional SMW property-name and structured-URL mappings. Without an override, use the field label in the wiki content language. |
| `$wgSWSEnableDevelopmentUI` | false | Boolean | Enable development interfaces; not needed for normal authoring. |
| `$wgSWSEnableInWikiConfig` | true | Boolean | Allow four settings on MediaWiki:SWS; page values override LocalSettings. false disables that configuration path. |
| `$wgSWSEnableLua` | true | Boolean | Register mw.sws when Scribunto is loaded; parser functions remain independent. |
| `$wgSWSEnforceValidation` | false | Boolean | Reject writes that introduce new error-severity violations; warnings do not block. |
| `$wgSWSAutoRenderMainSubject` | true | Boolean | Automatically render the main entity in the page view. false retains the Data tab and explicit rendering. |
| `$wgSWSValidationDebounceMs` | 300 | Integer ≥ 0 | Milliseconds before editor validation; 0 disables the timer, not blur/pre-save validation. |
| `$wgSWSQueryLimits` | `default`: 30 s / 5000; `expensive`: 300 s / 50000 | Array; see suboptions below | Per-tier query limits. Cypher uses timeout and row cap; SPARQL uses the timeout only. |
| `$wgSWSNeo4jInternalWriteUrl` | null | `null` or Bolt URL | Write connection for optional Neo4j projection; not required for local SWS values. |
| `$wgSWSNeo4jInternalReadUrl` | null | `null` or Bolt URL | Neo4j read/query connection, preferably with separate restricted credentials. |
| `$wgSWSRdfBaseUri` | null | `null` or URI string | Base of generated RDF IRIs; null uses CanonicalServer. Changing it changes identity URIs and requires planned reprojection. |
| `$wgSWSSubjectFirst` | false | Boolean | false: entities live on their originating page; true: new entities receive their own pages and entity-oriented navigation. |
| `$wgSWSDataLanguages` | [] | Array of BCP-47 language tags | Ordered data/input languages; [] uses the wiki content language. No automatic translation. |
| `$wgSWSSubjectPageNamespace` | 7480 | Integer: content namespace | Namespace for standalone entity pages when SubjectFirst=true; ignored otherwise. Default 7480: Entity. |
| `$wgSWSAutoRebuildOnMappingChange` | false | Boolean | Automatically rebuild affected graph stores after a mapping is saved, deleted or restored. |
| `$wgSWSSparqlStores` | [] | Array of store entries | Optional SPARQL stores. The first valid store serves the generic SPARQL query surfaces. |
| `$wgSWSPrefixSearchEnabled` | true | Boolean | Replace the main search field with namespace-scoped suggestions. |
| `$wgSWSPrefixSearchNamespaces` | null | `null` or array of integers | null: all registered non-negative namespaces; array: offered namespaces and order; []: no global replacement. No automatic administrator exemption. |
| `$wgSWSPrefixSearchSubstringFallback` | true | Boolean | Fill suggestion slots with title substring matches: explicit namespace, at least three characters, working CirrusSearch regex support required. |
| `$wgSWSAdditionalSubjectNamespaces` | [] | Array<Integer> | Enable data on namespaces beyond content namespaces, including subpages and non-wikitext models. SWS management pages and virtual namespaces stay excluded. |
| `$wgSWSBaseBridgeLocalSiteId` | null | `null` or string | Explicit site ID of this wiki in the local Wikibase repository. Not an item Q-ID or a Fuseki store name. Registered by bundled SchemaBaseBridge. |

### Example assignments

```php
$wgSWSSourceDataMode = 'store';
$wgWikiSchemaSmwMapping = ['properties' => ['S1' => ['F12' => 'Geburtsort']]];
$wgSWSEnableDevelopmentUI = false;
$wgSWSEnableInWikiConfig = true;
$wgSWSEnableLua = true;
$wgSWSEnforceValidation = true;
$wgSWSAutoRenderMainSubject = false;
$wgSWSValidationDebounceMs = 300;
$wgSWSQueryLimits = ['default' => ['timeoutSeconds' => 30, 'maxRows' => 5000], 'expensive' => ['timeoutSeconds' => 300, 'maxRows' => 50000]];
$wgSWSNeo4jInternalWriteUrl = 'bolt://USER:PASSWORD@127.0.0.1:7687';
$wgSWSNeo4jInternalReadUrl = 'bolt://READER:PASSWORD@127.0.0.1:7687';
$wgSWSRdfBaseUri = 'https://wiki.example.org';
$wgSWSSubjectFirst = false;
$wgSWSDataLanguages = ['de', 'en'];
$wgSWSSubjectPageNamespace = 7480;
$wgSWSAutoRebuildOnMappingChange = false;
$wgSWSSparqlStores = [['name' => 'sws', 'updateUrl' => 'http://127.0.0.1:3331/sws/update', 'queryUrl' => 'http://127.0.0.1:3331/sws/query', 'projection' => 'native']];
$wgSWSPrefixSearchEnabled = true;
$wgSWSPrefixSearchNamespaces = [NS_MAIN, NS_CATEGORY, NS_TEMPLATE];
$wgSWSPrefixSearchSubstringFallback = true;
$wgSWSAdditionalSubjectNamespaces = [828, NS_TEMPLATE, NS_MEDIAWIKI];
$wgSWSBaseBridgeLocalSiteId = 'mywiki';
```

### Suboptions: `WikiSchemaSmwMapping`

`properties` maps schema → technical field key → SMW property name, for example `['S1' => ['F12' => 'Geburtsort']]`. Ordinary content-language labels require no mapping. Reprocess affected pages after a change. The same mapping controls field notes, with the configured message suffix appended. Historical field-key mappings can remain usable after key transfers; the current-key mapping wins.

`urlEntries` overrides structured URL subproperties:

| Key | Default property name |
|---|---|
| `url` | `Quellen-URL` |
| `label` | `Quellentitel` |
| `caption` | `Quellenbezeichnung` |
| `captionPage` | `Quellenbezeichnungsseite` |
| `field` | `WikiSchema-Feld` |
| `subject` | `WikiSchema-Subjekt` |

Omitted URL entries retain their defaults. Explicit URL caption metadata may supply the intended semantic target name. Property names must not be empty or contain SMW parameter delimiters such as `=` or `|`. The note suffix is the `sws-notes-property-suffix` MediaWiki message, not a LocalSettings option. The automatic ranked page-description property is named `Kurzbeschreibung`; field mappings apply to explicit schema fields.

### Suboptions: `SWSSparqlStores`

| Entry key | Default / requirement | Use |
|---|---|---|
| `updateUrl` | Required | SPARQL 1.1 Update endpoint. |
| `queryUrl` | `updateUrl` | Query endpoint; typically explicitly `/query` for Fuseki. |
| `accessToken` | Unset | Bearer token for queries and updates. |
| `projection` | `'native'` | Native projection or configured mapping target. |
| `name` | `projection` value | Unique rebuild/status name; maximum 255 bytes. `neo4j` is reserved case-insensitively. |

Invalid or duplicate entries are skipped with a log warning. Generic SPARQL query surfaces use the first valid entry. Keep credentials on the server, outside wiki-page settings and public examples.

### Suboptions: `SWSQueryLimits`

| Tier | Selection | `timeoutSeconds` | `maxRows` |
|---|---|---|---|
| `default` | Without `apihighlimits`; also parser output independently of the triggering user | 30 | 5000 |
| `expensive` | API/Lua contexts with `apihighlimits`, where selected by that interface | 300 | 50000 |

Retain both tiers and their keys; use positive integers. Cypher uses both limits. SPARQL uses only the timeout; specify `LIMIT` in the query when needed. `maxRows` does not truncate SPARQL result documents.

### Settings on `MediaWiki:SWS`

With `$wgSWSEnableInWikiConfig = true`, these four JSON-page keys may override their LocalSettings values:

| Page key | Server setting |
|---|---|
| `subjectFirst` | `$wgSWSSubjectFirst` |
| `dataLanguages` | `$wgSWSDataLanguages` |
| `enforceValidation` | `$wgSWSEnforceValidation` |
| `autoRenderMainSubject` | `$wgSWSAutoRenderMainSubject` |

Omitted keys keep the server value. Editing requires `editinterface` and `editsitejson`; JSON and settings are validated on save. Infrastructure, credentials and all other SWS settings stay server-side.

## 3. Requirements, installation and integrations

| Component | Purpose / qualification |
|---|---|
| MediaWiki/PHP | Manifest minimum MediaWiki `1.47.0-wmf.24`, PHP 8.3; live-qualified on PHP 8.3.36. Later core versions require separate qualification. |
| Parser | Legacy parser for this release. `$wgUseParsoidParser = false` and `$wgUseParsoidLinksUpdate = false` are MediaWiki settings. |
| Database | MySQL/MariaDB, PostgreSQL and SQLite schema files are included; this is not equal live-test coverage of all backends. |
| Installation | Composer 2 and existing MediaWiki dependencies; helper requires Python 3.9+. Browser builds are included, so the wiki server needs no Node/npm build. |
| Skin | Qualified with Vector 2022; tablet/desktop input, 12-inch design minimum. |
| SchemaBaseBridge | Bundled, separately loaded source/data-view integration. |
| Scribunto | Required for Lua reads and schema templates that invoke Lua. |
| Wikidata/local Wikibase | Configured sources and page/entity associations; external source functions must be provided by their own extensions. |
| SMW | Optional annotation/query system, separate from the SWS store. Property datatypes must match. Enable additional semantic namespaces separately. |
| SHORTDESC | A working SHORTDESC provider is required for the highest description rank; a plain text field alone does not implement this magic word. |
| CirrusSearch | Optional advanced title suggestions; supplemental substring matching requires working regex support. SWS switches do not install search plugins or indexes. |
| SPARQL/Fuseki/QLever | Optional graph/query service; needed for the described Graph Explorer, not local data entry/direct field reads. |
| Neo4j | Optional graph projection/Cypher backend. |
| MCP | Separate authenticated service with permitted tools; no SWS setting installs it. |

Basic activation in the existing LocalSettings.php:

```php
$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;
wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension(
    'SchemaBaseBridge',
    "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json"
);
// Optional: use Data rather than an automatic article infobox.
$wgSWSAutoRenderMainSubject = false;
```

External configuration is not part of the 22 SWS/bridge options:

- MediaWiki: `$wgLanguageCode`, `$wgContentNamespaces`, `$wgCanonicalServer`, `$wgUseParsoidParser`, `$wgUseParsoidLinksUpdate`.
- Semantic MediaWiki: `$smwgNamespacesWithSemanticLinks`, configured separately for additional namespaces.
- CirrusSearch/OpenSearch: backend/plugin/regex/completion settings belong to their services.
- `MediaWiki:Article` labels namespace 0 in the SWS selector. Interface text and note suffixes are MediaWiki messages, not a new label-configuration array.
- Public identity-IRI routing, graph proxy rules and Graph Explorer service configuration are deployment tasks, not automatically installed by SWS.

Run MediaWiki's updater during installation and keep jobs running. `SemanticWikiSchema:RebuildData` reprocesses entity pages; `--refresh-sources` additionally requests a refresh of configured sources. Choose the scope of rebuilding for the task at hand.

For optional Q-ID registration, see the [helper setup and require_once example](../operations/install/integrations.md#optional-wikidata-registration-helper) and [helper settings](../../integrations/registration/README.md). The helper requires UnlinkedWikibase and is unnecessary when the association already exists.

## 4. AI-assisted installation, support and schema design

**We recommend first connecting your existing MediaWiki to an AI through a suitable MediaWiki MCP Server, then using that assistance to install and configure SWS.** General wiki tools can be used before SWS is installed. This is a recommended workflow, not a prerequisite for manual installation.

MCP exposes the tools made available to the AI. The operations it can perform depend on the server, its SWS support and the connected wiki account's permissions. SWS-specific tools become available after SWS is installed and their support is configured in the MCP Server. Wiki MCP access does not replace server access: Composer, LocalSettings.php and maintenance scripts require appropriate server tools or execution by the operator.

### Recommended workflow

1. **Connect the existing wiki:** Install the MCP Server, connect the AI and verify a read-only wiki operation.
2. **Prepare installation:** Ask the AI to inspect the MediaWiki version, installed extensions and desired functionality. Derive the required installation/configuration steps and create a backup before changes.
3. **Install and configure SWS:** The AI can prepare suitable configuration and commands and execute them with appropriate server access. Otherwise, the operator performs the steps and the AI helps check the results. Configure integrations required for the selected features.
4. **Verify behavior:** Use a suitable test page and a small schema; save data and check output, jobs and the desired source/SMW/search integration.
5. **Continue authoring:** Enable SWS tools in the MCP Server and use AI assistance for schema design, data maintenance and queries.

### How AI can help

| Task | Possible assistance |
|---|---|
| Installation/configuration | Inspect the environment, select applicable settings, prepare steps and check available results. |
| Schema design | Translate requirements into fields, types, labels, relations, source bindings, defaults and notes; inspect schemas and create/edit them with suitable write tools. |
| Data maintenance | Compare source values with local entries, edit entities and carry out traceable transfer procedures. |
| Queries/output | Write direct SWS queries, templates, Lua output and semantic/graph queries where the respective backend is configured. |
| Troubleshooting | Inspect errors, processing status, jobs and persisted values; narrow down causes and prepare appropriate corrections. |
| Guidance | Explain the features needed for the current task and develop examples for the actual wiki. |

An initial request could be:

> Inspect my connected MediaWiki and help me install and configure SWS. First determine the current environment and the prerequisites for the features I want to use. Explain which steps your tools can perform and which require me to work on the server.

AI assists the work; responsible users retain the review of research claims and approval of intended changes. For project-specific assistance: **info@aufeinwort.info**. Installation and data questions are handled against the particular system.

Further references: [Parser functions](../authoring/parser-functions.md), [Lua](../authoring/lua-api.md), [Field keys](../authoring/field-identifiers.md), [REST](../api/rest-api.md), [RDF](../api/rdf-export.md), [Maintenance](../operations/maintenance.md), [Installation](../operations/install/README.md).
