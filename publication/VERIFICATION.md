# Verification: SWS 0.6.0

- The operator confirmed the installed beta.2 runtime. Release preparation changes version metadata and packaging; extension runtime classes and built browser assets match that accepted candidate.
- 678 PHP tests / 1184 assertions and all 74 standalone contracts pass.
- 3857 frontend tests in 249 files and the browser build passed for the accepted beta.2 runtime. The final GitHub workflow repeats the frontend suite.
- 13 first-installation tests and 15 folder-upgrade tests pass. The actual Composer first-installation test resolves dependencies and loads AEWI classes in a controlled MediaWiki directory layout; it is not a fresh running wiki/database acceptance test.
- 2722 MCP tests pass. Fifteen patch-core tests and exact-base apply/idempotence/rollback checks cover each supplied MCP profile.
- Release archives are built from the committed Git tree. Every source file and installer payload is covered by SHA256 checks; the folder upgrade is tested with the exact packaged source against the supported 0.5.x inputs.

The upgrade guide distinguishes source-data preservation from derived annotations and queued page work. No live database is modified by release preparation.
