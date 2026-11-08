# Installing Semantic WikiSchema 0.1.22

For a fresh installation use the root README Composer instructions and the tested prerequisites. The installation directory is extensions/SemanticWikiSchema; load SchemaBaseBridge from its contained directory. Existing NeoWiki and earlier SWS installations must follow [MIGRATION-0.1.22.md](MIGRATION-0.1.22.md) before replacing code. The archive does not migrate database contents automatically.

0.1.22 has been installed by a coordinated folder replacement after migrating the adapted 0.1.20 installation on AufEinWort. A fresh Composer installation of 0.1.22 is not yet verified. Do not perform an unattended Composer update over a pre-migration database.

See [VERIFICATION-0.1.22.md](VERIFICATION-0.1.22.md) for validation and limits. NeoWiki and SWS 0.1.22 cannot directly share their renamed slot/model/table/API identifiers. This does not imply that all data or concepts are incompatible; supported transitions require migration.
