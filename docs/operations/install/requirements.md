# Requirements

For the wiki operator deciding whether to install this release.

| Component | Requirement / qualification |
|---|---|
| MediaWiki | Manifest minimum: 1.47.0-wmf.24. This is the live-confirmed core version; later core releases need their own qualification. |
| PHP | 8.3 or newer required by the manifest; live-confirmed on 8.3.36. |
| Parser | Use the legacy parser for this release. The configuration page supplies the settings. |
| Composer | Composer 2 available to the installation account; the working MediaWiki must already have its core dependencies. |
| Composer local configuration | MediaWiki's root composer.json must load composer.local.json through wikimedia/composer-merge-plugin. |
| Helper | Python 3.9 or newer. PHP and Composer executable paths can be supplied explicitly. |
| Database | A backend supported by your MediaWiki installation. SWS includes MySQL/MariaDB, PostgreSQL and SQLite schema files; these are not a claim of full runtime tests on all three servers. |
| Skin | Vector 2022 is the tested UI. The forms are designed for tablet/desktop input, with a 12-inch tablet as the design minimum. |
| Browser build | Included. No npm, Node.js or frontend build is needed for installation. |

**Optional:** Scribunto for Lua queries; SchemaBaseBridge for the source/Data-view integration; Semantic MediaWiki for automatic annotations; a configured Wikidata/local Wikibase source; CirrusSearch for its advanced title-suggestion behavior; Fuseki or another SPARQL service for the Graph Explorer. Local schema data entry does not require SMW, Wikibase, Fuseki or Neo4j.

Use the MediaWiki installation account, with write access to its Composer files and extensions directory. Do not place backup files containing local Composer configuration under the web root.

[Next: first installation](first-installation.md)
