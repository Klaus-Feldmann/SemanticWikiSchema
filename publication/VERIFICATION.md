# Verification of the prepared test snapshot

## Repeated during publication preparation, 2026-11-03

- Data UI: 3,732 tests in 228 files passed against this publication copy.
- PHP schema/value/RDF/historical-date/read-model suites: 494 tests, 784 assertions passed using PHPUnit 9.6 and isolated test dependencies.
- Isolated REST/identity, page-field and executable-Wikitext behavior contracts passed. These use MediaWiki stand-ins, not a complete new MediaWiki installation.
- Current Lua module snapshots: 30 module/date checks, 202 schema-field checks and 24 structured-URL checks passed.
- Graph: 68 unit tests; DOM checks for identity, field order/links, page values, structured URLs, Wikitext, descriptions and own/remote images passed. Fresh dependency installation and guarded build reproduced the current deployed graph asset hashes; original public bundle exports were retained.
- MCP: source patch applied to the stated official 0.20.1 files and matched the supplied source files. Runtime installer dry-run, application and idempotent repeat passed against the official 0.20.1 npm distribution.
- Runtime extension source and Data UI JS/CSS remain the tested snapshot. Publication-only changes normalize dependency paths in the debug map, make test paths portable, refresh the graph dependency lockfile, retain only active graph bundles, add notices/docs/integrations, and archive upstream production-publishing workflows.

## Prior live evidence for the included runtime

The running AufEinWort installation reported NeoWiki 0.1.8-aewi+2026-11-03 and NeoWikiBaseBridge 0.17.0+2026-11-03. Authenticated complete JSON, Turtle and TriG downloads succeeded for Cusanus, Goethe and Copernicus. Complete JSON matched the MCP Data view excluding request timestamps; both RDF formats parsed and preserved the complete read snapshot and equivalent triples. Single-subject JSON succeeded for all three, and single-subject RDF was also checked for Goethe. Invalid format/language requests were rejected. These checks read data and did not edit research pages.

The user previously confirmed own/source value handling, page/URL/image fields, description precedence, schema Wikitext execution and automatic SMW annotation processing in the same environment. External-source propagation remains dependent on page reprocessing. The publication packaging has not been installed on a second clean wiki, and no new GitHub CI run is claimed.

## Reproduce locally

Use Node 24+, PHP 8.3+ and the project dependencies. For isolated PHP checks, install the requirements in `publication-tests/dependencies` and point `NEOWIKI_TEST_VENDOR` to that vendor/autoload.php. Run PHPUnit 9.6 with `-c publication-tests/phpunit.xml`; run each `publication-tests/*-contract.php` as a separate PHP process. The full original MediaWiki integration suite needs a surrounding MediaWiki installation.

In resources/ext.neowiki run `npm ci`, then `npm test -- --run`. The prepared JS/CSS are included; a new build uses `npm run build`.

In graph-explorer-dev run `npm ci --ignore-scripts`, `npm test`, `npm run build`, `node tests/compare-build.mjs`; run the `*dom*.mjs` scripts individually for the rendered-node checks. These are local test fixtures, not a browser session against production.

In integrations/smw/tests run `npm install --ignore-scripts` then `npm test`. NEOWIKI_LUA_RUNTIME may point at an existing fengari module. The supplied source/wiki naming is the German tested setup.

For the independent MCP source/runtime checks, follow integrations/mcp/README-JEROEN.md. Its earlier full-project typecheck/build and 2,453-test result is historical evidence for the unchanged patch, not a full MCP suite rerun during this packaging step.

## Renamed Composer candidate, 2026-11-03

SemanticNeoWiki 0.1.9-test / Bridge 0.18.0-test renames the registry, ResourceLoader install path, Composer identity, bridge dependency and maintenance extension checks. Existing persisted/data/API/config identifiers remain unchanged. The registration integration and SMW patch were supplied by the user; only whitelisted non-secret LocalSettings entries are published.

Repeated PHP 494/784 and Lua checks passed, as did REST/page/Wikitext contracts and a registry/asset/path rename contract. The updated MCP patch passed typecheck, build and 2,454 tests. Its installer was checked on the official distribution and the earlier complete-view-patched state. The SMW correction was compared with official 7.3.1 and tested for dry-run, backup application and idempotence.

A local Composer path-repository installation into an isolated root installed the package in extensions/SemanticNeoWiki and loaded its domain classes through Composer. This verifies packaging, not a MediaWiki server boot. GitHub retrieval and production migration are separate checks.

## GitHub Composer check

The published 0.1.9 candidate was fetched through Composer from GitHub and installed in extensions/SemanticNeoWiki; all 2,320 initially inventoried files matched and Composer autoload resolved the domain classes. macOS system unzip warned on an inherited demo-data filename, then Composer ZipArchive completed successfully. The distribution was refined to omit demo/Docker-only directories; a fresh check of that refined archive is recorded in the local publication report. This still does not boot or update the production MediaWiki.


## Private processing 0.1.10, 2026-11-04

The earlier 0.1.9 Composer migration was completed on the production research server; its loading, Data and Graph behavior were confirmed during deployment. This supersedes the earlier preparation-stage statements above that the rename was awaiting a live test.

For this update an isolated full MediaWiki 1.47.0-wmf.24 installation, PHP 8.3.36, SMW 7.4.0, Scribunto and SQLite were used. Anonymous reading stayed disabled. Tests used the actual Lua modules and template, not only stand-ins. They covered full-revision/schema execution, own values, changed/cleared values, independent manual annotations, URL subobjects, error/access-denial retention, stale snapshot rejection, SMW UpdateJob, normal refresh-links, the actual job runner, moves/deletion, ordinary execution without SMW loaded, account-name collision and the registered publication-policy contract. HTTP tests checked authenticated purge/view and denied anonymous page, query, ask, Data-view and REST-export access. No privileged HTML result was written to ParserCache. Reproducible guarded fixtures are in [publication-tests/private-processing](../publication-tests/private-processing/README.md).

The runtime was then installed on AufEinWort as the guarded 0.1.10 file update, over its Composer-installed 0.1.9 base. With anonymous reading disabled, the separate page `Benutzer:Sophos/SemanticNeoWiki-Privat-Test` and `Schema:Privattest` were used. After an internal update, MCP read back `Prüfwert = Lesetest C` and `Kontrollwert = bleibt` from SMW. A subsequent subject save set `Lesetest D`; normal `neowikiSchemaWikitextRefresh` jobs completed, and MCP confirmed D in both NeoWiki and SMW. The field was then intentionally emptied. After the next normal job run, MCP confirmed that Prüfwert was absent and Kontrollwert remained. These checks inspected the stored results, not just save/job success messages. The maintainer additionally reported a successful Person update for Nikolaus von Kues with the existing local Wikibase sitelink preserved.

The supported operational workflow is saving data followed by processing through a running job worker. No new LocalSettings line or manual processing-account setup is required. The previous full UI/Graph/MCP suite results remain historical evidence for unchanged assets and integrations; they are not represented as a rerun for this PHP processing update. SQL/Fuseki do not gain a distributed transaction, and external source changes still require processing and remain subject to source-cache lifetimes.


## Publication preparation 0.1.13, 2026-11-04

The three guarded runtime updates (0.1.11 automatic PHP annotations, 0.1.12 initial values, and 0.1.13 parser warning handling) were applied to a fresh copy of remote master 4f1fae6916d8c467c4f84b0e57ce9930e0eeb130. Every before-hash matched. Runtime files and built Data UI assets match the maintainer-tested patch files. The maintainer confirmed successful live use, including automatic annotations, new-subject defaults, and duplicate-sort-key warnings no longer blocking processing.

The publication copy passed 3,740 Data UI tests in 230 files, and 509 isolated PHP tests with 836 assertions. The historical Lua annotation reference passes its 256 checks; it is kept under tests/legacy only. The current read-only Lua adapter passes 10 separate checks. REST/page/identity/export/rename/Wikitext contract checks use stand-in host services and are not a new production installation. New annotation cases exercise configurable names, own/source precedence, scalar values, dates, and errors. Existing Graph and MCP integrations are unchanged; their earlier test results above are historical evidence, not newly repeated full integration suites.

These results do not certify a stable 1.0 release or a clean installation on a second research server. No GitHub push or CI run is claimed by this local preparation.

An isolated Composer path-repository install of the prepared 0.1.13 working tree succeeded in `extensions/SemanticNeoWiki`; Composer autoload resolved the new AnnotationPlanner class from that installed directory. This checks the current package locally, not retrieval of an unpublished GitHub branch or a new MediaWiki server boot.

## Publication 0.1.17, 2026-11-06

Runtime verified on the existing AufEinWort installation after guarded updates. Dependent subjects referenced by the main subject on the same page contribute to automatic SMW annotations. The bundled Graph Explorer accepts language-tagged Wikidata text values. Kurt Gödel’s dependent field `Einordnung` was verified in SMW by the maintainer. The prepared runtime files were compared with the tested baseline and update payload. The full Composer package for this version has yet to be deployed on the production server.

## 0.1.19 guided field-type changes, 2026-11-08

The candidate derives from the published 0.1.18 tree. Five added regression cases cover changes from a P31-bound text field to Wikitext, Number, Image and Page, and re-selection of the unchanged type. All 230 frontend test files passed, comprising 3747 tests. TypeScript checking and the production build passed. The new notice is registered in the ResourceLoader module and translated in English and German. The maintainer installed the complete local 0.1.19 package on the M4 production server and confirmed schema editing and Wikidata retrieval with the job worker enabled. Local Composer installation and the PHP contract checks passed. Installation of 0.1.19 through GitHub/Composer remains a separate check.
