# Composer installation and switch from the tested NeoWiki-named fork

The sole supported installation path is Composer in an existing MediaWiki 1.47.0-wmf.24 server matching the environment in README.md. There is no supported Docker/demo install. The upstream docs retained in this repository describe the original project and are not an alternative installation channel for this fork.

## Switch from Semantic NeoWiki 0.1.17 to Semantic WikiSchema 0.1.18

1. Back up the Composer files, LocalSettings.php, the installed extension and its graph-explorer/config.js. If you use MCP, apply the updated runtime patch before changing the wiki registration.
2. In the MediaWiki root Composer configuration, point the VCS repository at the renamed GitHub repository once it is available. Replace the requirement for `aufeinwort/semantic-neowiki` with `"aufeinwort/semantic-wikischema": "dev-semantic-0.1.19"`. Remove a merge include of the old extension composer.json if present.
3. From the MediaWiki root, preview the switch using `--dry-run`, then run the same command without it.

```sh
composer update aufeinwort/semantic-neowiki aufeinwort/semantic-wikischema --with-dependencies --no-dev --prefer-dist --no-scripts --dry-run
```

Composer removes the old package and installs the new one into `extensions/SemanticWikiSchema`. Coordinate the Composer and LocalSettings changes in the same maintenance window. Verify the new files before switching the loading lines.

```php
wfLoadExtension( 'SemanticWikiSchema' );
wfLoadExtension( 'SchemaBaseBridge', "$IP/extensions/SemanticWikiSchema/SchemaBaseBridge/extension.json" );
```

If you load the registration helper from the extension directory, update its include path to the new directory. Restore your saved graph-explorer/config.js in the new directory. Update bookmarks and the existing Graph Explorer redirect to `/w/extensions/SemanticWikiSchema/graph-explorer/envendpoint.html` on a `/w` installation. Preserve the SPARQL route and RDF base IRI.

Rebuild the localization cache and verify both extension names in Special:Version. Open a known subject under Data and check a graph node and an export. Check MCP after restarting its connection. An installation already using the 0.1.17 Wiki modules can keep them. There is no schema change for this rename.

## Updating 0.1.18 to 0.1.19

Update the existing requirement to dev-semantic-0.1.19. Save the deployment-specific graph-explorer/config.js outside the extension directory. Preview and then apply the Composer update:

~~~sh
composer update aufeinwort/semantic-wikischema --with-dependencies --no-dev --prefer-dist --no-scripts --dry-run
~~~

After reviewing the preview, repeat without --dry-run. Restore the saved graph configuration, rebuild the localization cache and reload the schema editor from the origin. Existing loading statements, package name and installation directory continue to apply. This complete package supersedes the incremental field-type patches; removing a previous field-type patch first is unnecessary.

The editor clears the old P mapping on a real field-type change and displays a notice. A supported target type can receive a newly selected mapping. This update does not convert previously stored subject values.

## Before changing the server

Back up composer.json, composer.local.json, composer.lock, LocalSettings.php, the old NeoWiki extension directory and graph-explorer/config.js. Preserve your existing database backup. First apply the expanded MCP patch if the MCP tools must remain available after the extension is renamed. No installation command in this guide implicitly changes LocalSettings or patches SMW/Wikibase.

The current server may have loaded NeoWiki from copied files and a Composer merge include rather than as a managed package. Check which case applies. Remove old `neowiki/neowiki` requirements and the obsolete `extensions/NeoWiki/composer.json` merge include when switching; retain all unrelated merge entries and package requirements. Do not run `COMPOSER=composer.local.json composer update`, which would treat the local fragment as a separate root project.

## Install the candidate

Merge the VCS repository, package requirement and composer/installers allowance shown in README.md into your existing root/local configuration. Use `dev-semantic-0.1.19` explicitly. Then run the README Composer command from the MediaWiki root. The extension installer writes `extensions/SemanticWikiSchema` and its dependencies are loaded through the MediaWiki root vendor/autoload.php.

Check that `extensions/SemanticWikiSchema/extension.json` exists before changing loading instructions. Replace the NeoWiki and bundled Bridge load lines with those in README.md. The old directory may be retained for return but must not be loaded in parallel. Do not delete old dependencies, RDF data or namespace contents as part of the rename.

Keep `$wgUseParsoidParser = false` and `$wgUseParsoidLinksUpdate = false`, the confirmed legacy-parser selections. Retain the existing NeoWiki configuration names. `MediaWiki:NeoWiki` continues to provide its current allowed on-wiki settings. Source identities still come from UnlinkedWikibase page properties and local Wikibase sitelinks. A registration helper is included, but your working WikibaseQ/template workflow does not need replacement merely for the rename.

Copy your existing graph config to the new path and deliberately update your graph URL/redirect. The extension directory does not create a SPARQL proxy or move Fuseki data. Preserve the exact RDF base IRI and graph-store endpoints. A local Wikibase site-id override remains `NeoWikiBaseBridgeLocalSiteId`, if your site needs one.

## Process and check

The rename has no SQL or content-model migration. Rebuild localization with `php maintenance/run.php rebuildLocalisationCache --force`, refresh normal PHP/OPcache state under your established server procedure and reload the Data/Graph pages. A fresh installation requires MediaWiki's normal `php maintenance/run.php update` as well. No whole-wiki rebuild is required just for the renamed package.

Verify Special:Version reports Semantic WikiSchema and SchemaBaseBridge; open/edit/save one subject, inspect Data/Graph links and thumbnails, and download complete JSON/Turtle/TriG. Confirm the existing MCP tools and complete-view tool after connection refresh. For SMW, verify a changed and a removed value after saving the subject and running queued work. Keep a functioning job runner. Remote Wikidata changes alone do not reprocess all pages.

The renamed 0.1.9 Composer installation was confirmed on AufEinWort. The 0.1.10 private-processing runtime was subsequently applied there as a checked file update and verified using the stored SMW data. Those are distinct checks; a distribution download alone is not a server boot test.

## Updating from 0.1.9 and private operation

For a Composer-managed predecessor, use the package switch above. Do not change persisted content models, namespaces or existing NeoWiki configuration names. These changes introduce no SQL schema migration and require no whole-wiki rebuild. Preserve existing local configuration when updating. Rebuild localization after the file update.

Private wikis may keep `$wgGroupPermissions['*']['read'] = false`. No read whitelist for article/schema/module namespaces is required by the new processing path. A non-login system account, `SemanticNeoWiki internal processor`, is created automatically on first use. Its effective rights are restricted to `read`, including removal of inherited write/admin rights. An existing ordinary account with that name is never taken over. Do not set up a password or browser session for it.

Run the normal MediaWiki job worker. Saving subject/schema data schedules the derived processing; inspect stored SMW values after processing. A protected full-revision result is passed to normal secondary-data updates, not to the shared HTML parser cache. Normal views, REST/API and MCP keep the caller's permissions. Existing explicit ACLs and registered publication policies are respected.

Use one separate test subject before a larger migration. Verify a value, a changed value, an intentionally empty value, and preservation of an independent manual annotation. No changes to Wikibase or UnlinkedWikibase are part of this update. Creating new private-wiki sitelinks is a separate mechanism; the maintained existing sitelink was observed to remain intact.

## Optional companion files

Import/adapt only the wiki pages documented under integrations/smw. Existing current modules need not be replaced for the extension rename. Load AewiWikidataRegistrierung.php once if this helper is used; its Magic Words file is registered by that PHP file. The optional SMW statistics patch is constrained to exact 7.3.1 source hashes and applied only when relevant to your SPARQL-store setup. Keep the already required Wikibase/simple-cache and DataTransfer compatibility adjustments; do not infer that Composer repairs those automatically.

## Distribution files

GitHub/Composer archives exclude the inherited DemoData and Docker directories through .gitattributes. These are not part of the supported production install and can trigger legacy system-unzip filename handling on macOS. The install inventory is publication/DISTRIBUTION-SHA256SUMS; publication/SHA256SUMS describes the full source tree. The inventories do not hash themselves or each other.

## Current version 0.1.18

The full package includes the tested runtime updates. If migrating an installation that predates automatic annotation, see the Wiki module instructions in `integrations/smw`. Keep the existing module names and preserve `Modul:NeoWiki/config`. Old `{{NeoWiki}}` / `#invoke:NeoWiki|annotieren` calls become harmless. Remove annotation-only schema programs after updating both modules, without removing Q registration or unrelated schema Wikitext. Initial values apply to newly created subjects only; incomplete URL presets remain form data until completed. Do not apply old file patches on top of this full package.

No SQL schema migration is introduced by these updates. Keep the job worker operational. The current full Composer distribution still requires its own installation test; live evidence is from guarded updates on the existing production installation.
