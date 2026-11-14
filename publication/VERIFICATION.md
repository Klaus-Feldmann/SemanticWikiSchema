# Verification: Semantic WikiSchema 0.5.0

Release source: the operator-supplied `SemanticWikiSchema-0.5.00-alpha.8` directory from 2026-11-14. Runtime PHP, TypeScript and bridge sources are unchanged; the release updates version metadata, installer activation, documentation and publication checks. The separately published `mcp-sws-0.5.0` integration is retained byte-for-byte.

## Automated checks

- 566 PHP domain/projection tests with 979 assertions, plus 84 create/replace action tests with 150 assertions.
- 66 isolated processing contracts, including actual key-transfer writer/processor tests with controlled MediaWiki host services, source retention, idempotence, collisions and concurrent-edit refusal.
- 3,852 frontend tests in 248 files, TypeScript and browser build.
- 78 Graph tests, rebuilt public-bundle comparison and DOM checks.
- 266 Lua checks: 30 module, 202 schema-field, 24 structured URL and 10 current-reader checks.
- 13 first-installation helper checks and 2 package/message-registration checks.
- Actual Composer first installation using the candidate package: 21 resolved packages, correct extension path, S1/E1 autoload, runtime dependencies available, unrelated configuration and existing package versions preserved, LocalSettings.php unchanged. This uses a controlled directory layout, not a running MediaWiki.
- Release archives are built from committed Git content; source and installer manifests, archive CRC and individual file hashes are checked. The versioned installer pins Composer to 0.5.0.

## Live evidence and limits

The operator reports that alpha.8 was installed and `RebuildData` completed all 74 pages. The separate MCP 0.5.0 release records a 22-step live run and a write-protection check against this interface. These are separate from a fresh full MediaWiki installation test.

The qualified environment is MediaWiki 1.47.0-wmf.24, PHP 8.3, the legacy parser and Vector 2022. MediaWiki 1.47 stable, a fresh core/database installation, every SQL server, Neo4j live Cypher and arbitrary NeoWiki migrations are not qualified by these checks. Built browser assets and the installer do not install MediaWiki or optional services.

Existing SWS/NeoWiki installations are outside the first-installation helper. Contact the maintainer for the appropriate update or migration procedure. The MCP patch is a separate download and does not replace the wiki installer.
