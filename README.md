# Semantic WikiSchema

A research-oriented, independent fork of [NeoWiki](https://github.com/ProfessionalWiki/NeoWiki): schema-based data entry, Wikidata and local Wikibase sources, optional Semantic MediaWiki annotations, knowledge graphs and AI access through MCP.

The main purpose of this fork is to automatically generate Semantic MediaWiki (SMW) annotations from data retrieved on demand from Wikibase and/or Wikidata using selected property IDs (P-IDs), with customizable field labels that serve as SMW property names.

**Maintainer:** Klaus Feldmann / AufEinWort · **Contact:** [info@aufeinwort.info](mailto:info@aufeinwort.info) · **Research wiki:** [aufeinwort.info](https://aufeinwort.info/wiki/Spezial:Version)

**This fork is not maintained or supported by Jeroen De Dauw or Professional Wiki.** Please report fork-specific issues in [this repository](https://github.com/Klaus-Feldmann/SemanticWikiSchema/issues), not to the original NeoWiki project. Original authorship and licenses are retained.

Semantic WikiSchema was previously published as Semantic NeoWiki.

## Independent development and migration from NeoWiki

Semantic WikiSchema is developed independently from its NeoWiki foundation to provide its extended schema, Wikidata/Wikibase and optional SMW workflow. From **0.1.22**, it uses its own SWS database tables and columns, content-model names, subject slot, configuration names, Lua bindings, API routes and MCP tools. **NeoWiki and Semantic WikiSchema 0.1.22 are not interchangeable installations:** switching between them requires a coordinated migration rather than replacing extension files or sharing the same database structures.

A report-first migration tool supports moving existing legacy NeoWiki/Semantic WikiSchema identifiers to SWS while preserving data, subject IDs, pages and revision references. The migration from the adapted **Semantic WikiSchema 0.1.20** installation to **0.1.22** has been completed and tested on AufEinWort. Migration from other NeoWiki versions requires checking the actual source schema and migration report; it is not yet verified for every upstream release. Follow the [migration guide](publication/MIGRATION-0.1.22.md) before replacing an existing installation. Original NeoWiki authorship, provenance and licensing remain acknowledged.

## Dynamic, schema-driven metadata from WikiData

Semantic WikiSchema lets contributors define WikiData property mappings once in a shared schema, such as Person, instead of maintaining the same retrieval logic across individual articles, templates and Lua modules. When a page has a Wikidata item association, its schema provides the configured property values automatically. Adding a P-ID to the schema triggers background updates of the pages using that schema; contributors do not have to edit each article separately. Locally entered values can take precedence over retrieved values, preserving editorial decisions and research findings.

This structured workflow does not require [Semantic MediaWiki](https://www.mediawiki.org/wiki/Extension:Semantic_MediaWiki) to be installed. When SMW is installed, Semantic WikiSchema also automatically generates semantic annotations from the schema’s field names and values, including values retrieved through configured P-IDs from Wikidata or a local Wikibase. These annotations make the data available for semantic queries and tables.

For Wikipedia, this offers a potential way to consolidate metadata tasks currently distributed across templates such as the German Wikipedia’s Normdaten and Personendaten, reducing repeated manual entry and maintenance. Less experienced contributors could work with a consistent data-entry form while experienced contributors maintain the shared schema. The current implementation demonstrates this workflow in a research wiki; replacing Wikipedia-specific templates would require mapping and validating their individual functions.

## Version and supported environment

Semantic WikiSchema **0.1.22** has been installed on AufEinWort after migrating the existing 0.1.20 data. The user confirmed the browser workflow, and authenticated MCP reads verified schema definitions, stored subjects, dependent-subject relations and complete source-aware Data views through the new SWS APIs. NeoWiki 0.1.0 remains the original upstream base.

See [verification](publication/VERIFICATION-0.1.22.md) for the exact checks and remaining limits. A fresh Composer installation of 0.1.22 and fresh JSON/Turtle/TriG export downloads have not yet been verified; earlier-version checks are identified separately.

Support is limited to the tested setup: **MediaWiki 1.47.0-wmf.24, PHP 8.3.36, the legacy parser, UnlinkedWikibase 3.0.0, Semantic MediaWiki 7.4.0, Semantic Scribunto 3.0.0, local Wikibase, Scribunto and a Fuseki/SPARQL-backed Graph Explorer.** Earlier MediaWiki versions and other parser/backend combinations are not supported or tested by this fork. Later versions require their own verification. Details are in [the environment snapshot](publication/TESTED-ENVIRONMENT.json).

Schema fields can be adapted to a new type directly in the editor. The editor initializes the settings for the selected type, clears the previous external property mapping and guides contributors to select a compatible Wikidata or Wikibase property. Field names and descriptions are retained, and selecting the current type preserves its property mapping. Existing subject values retain their stored representation.

## Added capabilities

- Source-qualified Wikidata/local Wikibase Q identities and schema-level P lookup by ID or label.
- Read-only source values with explicit own-value precedence and configurable local/source links.
- Historical BCE dates with precision/calendar information and year links.
- Images from local files, Commons or bound source properties, including Data/Graph thumbnails.
- Local page selection, structured source URLs and display names, schema presets, passive Wikitext and centrally executed schema Wikitext.
- Automatic schema-driven SMW annotations; no annotation template call is required in articles or schemas. Individual Lua queries remain available.
- Private-wiki processing with an automatically managed, non-login, read-only identity; validated full-revision results feed the normal MediaWiki/SMW updates without granting anonymous read access.
- Complete Data-view JSON, Turtle and TriG downloads and a separately supplied MCP read-tool patch.
- Readable select values, ordered fields, configured links and preferred descriptions in Graph Explorer.

**Keep a functioning job worker running.** Data is saved first and derived page/SMW data is maintained through background processing. No additional processing-account settings are required. Existing article data is retained when a complete replacement cannot be prepared; intentionally empty fields remove their former annotations.

External source changes alone do not guarantee immediate propagation to stored SMW annotations; affected pages must be processed. Existing SMW property types and research decisions remain under the administrator's control.

## Installation: Composer only

**Existing installations must follow the [data migration guide](publication/MIGRATION-0.1.22.md) before switching code. Copying the new extension over an old installation does not migrate its data.**

This version is for an existing MediaWiki server. **No Docker image, Docker demo or alternate application installer is supplied or supported.** Built Data UI/Graph assets are included; npm is not an installation requirement. Inherited demo data and Docker reference files remain in Git history/source browsing but are excluded from GitHub/Composer distribution archives.

In the MediaWiki root, merge the following into your existing `composer.local.json` without replacing other settings:

```json
{
  "repositories": [
    { "type": "vcs", "url": "https://github.com/Klaus-Feldmann/SemanticWikiSchema" }
  ],
  "require": {
    "aufeinwort/semantic-wikischema": "dev-semantic-0.1.22"
  },
  "config": {
    "allow-plugins": { "composer/installers": true }
  }
}
```

Use the MediaWiki root Composer workflow, which merges `composer.local.json`; do not run this local configuration file as a separate Composer project. For an existing installation, replace the previous package requirement (`aufeinwort/semantic-neowiki` or `neowiki/neowiki`) with the new one. See the migration guide for the corresponding Composer update command. Remove an obsolete merge include of `extensions/NeoWiki/composer.json` or a duplicate `extensions/SemanticWikiSchema/composer.json` include, if present. Preserve existing compatibility work for Wikibase/DataTransfer; the fork does not automatically modify those packages.

Then, from the MediaWiki root:

```sh
composer update aufeinwort/semantic-wikischema --with-dependencies --no-dev --prefer-dist --no-scripts
```

The package installs to `extensions/SemanticWikiSchema`. It conflicts with a simultaneously installed official `neowiki/neowiki` package. Back up Composer files, existing extension files, LocalSettings and deployment-specific graph configuration before changing a working wiki. The [installation and migration guide](publication/INSTALLATION.md) covers the rest.

## LocalSettings.php and existing data

After the prerequisite extensions, use:

```php
wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension( 'SchemaBaseBridge', "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json" );

$wgUseParsoidParser = false;
$wgUseParsoidLinksUpdate = false;
$wgSWSAutoRenderMainSubject = false;
$wgSWSSubjectFirst = false;

// Optional AufEinWort registration helper; replace the old include, do not duplicate it:
$wgAewiWikidataAutoNamespaces = [ 0 ];
$wgAewiWikidataSmwProperty = 'Wikidata';
require_once "$IP/extensions/SemanticWikiSchema/integrations/registration/AewiWikidataRegistrierung.php";
```

Remove the former `wfLoadExtension( 'SWS' )` and old Bridge include for the switch; never load both implementations. Existing schema/subject content models, namespace IDs, `mw.sws`, `sws*` APIs, `SWS*` settings, custom hooks and `MediaWiki:SWS` are deliberately retained. **Do not rename stored data or these settings just because the extension folder changed.**

See the commented [LocalSettings example](publication/LocalSettings.SemanticWikiSchema.example.php), [graph configuration](graph-explorer/config.js), and [integration guide](publication/INSTALLATION.md). Keep the existing RDF base IRI and graph-store endpoints. The new graph page is under `/w/extensions/SemanticWikiSchema/graph-explorer/envendpoint.html` on a `/w` deployment; update old links/redirects deliberately. The SPARQL service remains external. The tested SWS graph dataset (port 3331) and SMW SPARQLStore dataset (port 3030) are distinct; the example preserves that separation.

## Included integration material

- [SMW modules and templates](integrations/smw/README.md), with current wiki revision provenance. Their existing names remain SWS. Import Modul:WikiSchema for queries and optional WikiSchema templates. Optional SMW mappings use $wgWikiSchemaSmwMapping in LocalSettings.php. An annotation template call is no longer needed.
- [Wikidata registration helper](integrations/registration/README.md): AewiWikidataRegistrierung.php and its Magic Words file. Existing Q-registration templates can stay; the example helper's automatic title search uses German Wikipedia and is namespace-controlled.
- [Historical optional SMW 7.3.1 statistics correction](integrations/smw/compatibility/README.md): exact-source patch for SPARQLStore, with a checked backup/apply script. It is not applied by Composer and must not be applied to SMW 7.4.0 on the basis of its older version alone.
- [MCP source/runtime patch](integrations/mcp/README.md): complete Data-view tool and recognition of the SemanticWikiSchema extension name. Apply before changing extension identity if MCP must retain the existing SWS tools. It targets MCP 0.20.1, with checked compatible 0.20.0 runtime files; it contains no authentication data.

## Credits, license and verification

**SchemaBaseBridge** — Softwaredesign: [Klaus Feldmann / AufEinWort](https://aufeinwort.info/), Programmierung: ChatGPT/Sophos.

**Semantic WikiSchema** — Softwaredesign und Weiterentwicklung des Forks: [Klaus Feldmann / AufEinWort](https://aufeinwort.info/), Programmierung: ChatGPT/Sophos. Basiert auf NeoWiki von [Professional Wiki](https://professional.wiki/), [Jeroen De Dauw](https://www.entropywins.wtf/mediawiki), Morne Alberts, [Alistair3149](https://www.mediawiki.org/wiki/User:Alistair3149) und [weiteren Mitwirkenden](https://github.com/ProfessionalWiki/NeoWiki/graphs/contributors?all=1).

Original copyright notices and the GPL-2.0-or-later license remain unchanged; see [COPYING](COPYING). Publication does not imply endorsement by the original developers or OpenAI.

Zazuko Graph Explorer retains its LGPL notice, original sources and dependency notices; the separate MCP patch retains the MCP project's license. See [third-party provenance](publication/THIRD-PARTY.md).

[Verification and limits](publication/VERIFICATION.md) · [German overview](publication/LESEMICH.md) · [Contributing](CONTRIBUTING.md) · [Original SWS documentation](README-UPSTREAM.md)
