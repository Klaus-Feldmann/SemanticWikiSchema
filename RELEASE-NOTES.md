# SWS 0.4.03

SWS 0.4.03 provides stable schema/entity identities, multilingual labels and a local store for source-derived research data. Schemas can be renamed by changing their label without breaking entity references.

The installation package includes the built Data UI, SchemaBaseBridge 0.21.1 and the corrected Graph Explorer. The schema list excludes retired redirect pages; the Data tab includes schema definitions for explicit S-ID references. Graph queries recognize `/schema-id/S…` and display stored schema labels.

The public installation path is a first installation in an existing MediaWiki. The helper checks for prior SWS/NeoWiki files and Composer packages, backs up the changed Composer files outside the web root, then installs `aufeinwort/semantic-wikischema:0.4.03`. LocalSettings, database updates and optional backends remain explicit operator steps.

**Qualification:** live-confirmed by the operator on AufEinWort after migration and functional tests, using MediaWiki 1.47.0-wmf.24 and PHP 8.3.36 with the legacy parser. This is a pre-1.0 release for that development-era MediaWiki environment; it is not a claim of validation against a future 1.47 stable release. Fresh Composer installation checks and isolated tests are reported separately in [verification](publication/VERIFICATION.md).

**Existing installations:** contact [info@aufeinwort.info](mailto:info@aufeinwort.info) before switching from SWS or NeoWiki. No automatic NeoWiki import is included.

[Installation](docs/operations/install/README.md) · [Deutsch](docs/operations/install/de/README.md)
