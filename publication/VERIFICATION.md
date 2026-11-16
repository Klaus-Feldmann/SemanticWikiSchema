# Verification: Semantic WikiSchema 0.5.02

## Automated checks

- 578 PHP domain/projection tests with 998 assertions; 84 create/replace action tests with 150 assertions.
- 68 separate processing contracts, including description ranking, source-mode selection, actual worker output, independent schema annotations and field-label SMW naming. MediaWiki/SMW services at these boundaries are controlled fixtures, not a live database.
- 3,854 frontend tests in 248 files; TypeScript check and frontend build.
- 78 Graph tests, bundle comparison and DOM checks.
- 266 Lua checks: 30 module, 202 schema-field, 24 structured URL and 10 current-reader checks.
- 13 first-installation helper tests and 2 publication/message-registration tests.
- Actual Composer first installation using the candidate package into a controlled MediaWiki directory layout: 21 packages, S1/E1 autoload and runtime dependencies available, unrelated configuration/package versions preserved, LocalSettings.php unchanged. No running fresh MediaWiki/database test is implied.

## Live qualification

The operator confirmed installation and tested Paulinus Mayr, Johann Wolfgang von Goethe and Nikolaus von Kues. Read-only checks verified unprefixed SMW field/note properties, no values for the tested obsolete schema-prefixed properties, ranked descriptions and corresponding actual PrefixSearch API responses. The active ranks were respectively SHORTDESC, Wikidata and local Wikibase. Multiple SMW description values coexist without changing the selected search subtitle.

Core: MediaWiki 1.47.0-wmf.24, PHP 8.3.36, legacy parser, Vector 2022. Live source mode: cache. Store behavior is covered by isolated contracts, not a new live mode switch. This is not qualification of MediaWiki 1.47 stable or every database/backend combination.

## Publication boundary

The published branch retains its existing processing diagnostics. The separately versioned external MCP adapter is unchanged. Only public English/German documentation is included; internal provenance/completeness review material is excluded.

Release archives are generated from committed Git content, with complete file manifests, archive CRC and individual SHA-256 verification. The installer pins Composer to 0.5.02. It prepares Composer files but does not activate extensions, alter LocalSettings.php, update a database or start jobs.
