# Semantic WikiSchema releases

## 0.1.19 — 2026-11-08

Schema fields can be adapted to a new type directly in the editor. The editor initializes the settings for the selected type, clears the previous external property mapping and guides contributors to select a compatible Wikidata or Wikibase property. Field names and descriptions are retained, and selecting the current type preserves its property mapping. Existing subject values retain their stored representation.


## 0.1.18 — 2026-11-06

Renames the extension and its companion to Semantic WikiSchema and SchemaBaseBridge. The package installs in `extensions/SemanticWikiSchema`. See the [migration guide](publication/INSTALLATION.md) for the Composer, LocalSettings, Graph Explorer and MCP changes.

## 0.1.17 — 2026-11-06

Runtime verified on the existing AufEinWort installation after guarded updates. Dependent subjects referenced by the main subject on the same page contribute to automatic SMW annotations. The bundled Graph Explorer accepts language-tagged Wikidata text values.

## 0.1.13 — 2026-11-04

Current research-workflow snapshot with automatic SMW processing, schema presets and consistent parser-warning handling. Existing field configuration and individual Lua queries are retained. Runtime confirmed on the documented AufEinWort installation; Composer packaging checked locally; GitHub retrieval follows publication.

# Release notes

## SemanticNeoWiki 0.1.10 test — 2026-11-04

* Private-wiki processing while anonymous reading remains disabled, using an automatically managed non-login identity restricted to read access.
* Full-revision preparation and validated hand-off to ordinary MediaWiki/SMW secondary-data updates and background jobs.
* Preservation of previous derived assertions on incomplete results; intentional value removal still clears old annotations. Independent manual annotations are retained.
* Optional SMW 7.4 integration, processing-state metadata, current-parse Wikidata identity preference, and the job transaction-round fix.
* No SQL schema, frontend asset or existing Wiki-module change. Keep the regular job worker running.
* On AufEinWort, own-value changes and clearing were verified by reading back stored SMW data with anonymous read disabled.

Composer branch: `dev-semantic-0.1.10-test`. The earlier `semantic-0.1.9-test` snapshot remains available. [Installation](publication/INSTALLATION.md) and [verification](publication/VERIFICATION.md).

To move an install to a newer version, see [Upgrading](https://neowiki.ai/docs/operations/upgrading).

## NeoWiki 0.1.0

The first release, a beta. Highlights:

* Structured data on wiki pages: Subjects, edited in forms generated from their Schema and versioned with the page
* Schemas with nine Property Types, including relations between Subjects, dates and language-tagged text, and
  constraints that block a save or only warn
* Infoboxes, with Layouts that choose which properties they show and how
* Cypher queries on Neo4j, SPARQL queries on SPARQL 1.1 stores, and RDF export, with Subjects mapped to ontologies such
  as EDM and CIDOC-CRM
* Parser functions, a Lua library, a REST API, and extension points for new Property Types, View Types and graph stores
* A Docker stack with demo data, to try NeoWiki in one command

Try NeoWiki at [neowiki.dev](https://neowiki.dev), or start with
[Installation](https://neowiki.ai/docs/operations/installation). [Capabilities](https://neowiki.ai/capabilities) tours
NeoWiki in depth.

### Docker stacks from before 0.1.0

The Docker stack no longer runs Neo4j, which Cypher queries need, unless you name it in `services=` each time you start
the stack, as in `make upgrade services=qlever,neo`. QLever, its SPARQL store, runs as before.

* Unless you keep Neo4j, run `make down` before `make upgrade`, or its container keeps running unused.
* Existing demo pages query Neo4j. `make import-demo-data` replaces them with pages that query QLever. Pages
  rendered before the import keep their old errors until MediaWiki runs the jobs it queues;
  `php maintenance/run.php runJobs` in `make bash` runs them at once.
