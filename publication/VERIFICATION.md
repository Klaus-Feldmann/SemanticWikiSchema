# Verification: SWS 0.4.03

## Installed runtime

On 13 November 2026 the operator supplied the installed SWS 0.4.03 directory and confirmed working Data views, editable schema labels, categories/SMW updates, source-data modes and the Graph Explorer. Runtime PHP, SQL, i18n, bridge, Data UI and active Graph files in this release are byte-identical to that supplied installation. Retired unused Graph bundles and macOS metadata are omitted. Graph build sources were aligned with the installed bundles.

This is operator-confirmed operation on the existing AufEinWort wiki, not an independently repeated fresh MediaWiki installation. The tested core is 1.47.0-wmf.24 with PHP 8.3.36 and the legacy parser.

## Candidate checks

- 543 standalone/domain PHP tests, 917 assertions; 57 separate PHP contract scripts.
- 3,826 Data UI tests in 242 files.
- 71 Graph tests, rebuilt bundle comparison and node-template / field DOM checks.
- 12 installer tests covering first-install refusal, preservation of settings, plugin policies, private backups and Composer failure reporting.
- Actual Composer first installation with the real package files and runtime dependencies into a controlled MediaWiki directory layout. The autoloader resolves S1/E1 domain types and the required libraries; unrelated configuration and existing fixture dependency versions are retained. This is not a running core/database test.

The release keeps runtime behavior unchanged. Four older test expectations were updated for stable RDF prefixes, separate schema-template/user-Wikitext types, a single authorized source-snapshot read and preservation of raw text whitespace; a raw planner row is not proof SMW stores a blank semantic value.

## Installation boundary

The helper prepares Composer configuration and installs a pinned package. LocalSettings activation, the database update, job supervision, first schema/entity checks and optional backends are operator steps. No NeoWiki import, automatic upgrade, database conversion or backend installation is claimed.

[Run the candidate checks](../.github/workflows/semantic-checks.yml) · [Installation](../docs/operations/install/README.md)
