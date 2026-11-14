# First installation

For the operator installing SWS files and dependencies. Check [requirements](requirements.md) first.

Download **SemanticWikiSchema-0.5.0-installer.zip** from the [release](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/v0.5.0), extract it outside the web directory and open its folder in a terminal.

Run a preview, replacing the MediaWiki path:

```sh
python3 install.py --mediawiki-root /path/to/mediawiki --dry-run
```

Then install:

```sh
python3 install.py --mediawiki-root /path/to/mediawiki --apply
```

If needed, add `--php /path/to/php` and `--composer /path/to/composer`. `--backup-dir /private/path` selects a backup location outside the MediaWiki web directory; the default is `.sws-installer-backups` in the installation account's home directory.

The helper adds the pinned SWS requirement and its GitHub VCS repository to composer.local.json. It enables the required merge/installer plugins in root composer.json, preserves unrelated settings and explicitly disabled plugin policies, and runs a targeted Composer update for the SWS package with dependencies. The changed Composer files and an existing lockfile are backed up in a private directory. Existing explicit Composer plugin permissions are retained.

Composer installs to `extensions/SemanticWikiSchema`. If Composer fails, stop and keep the diagnostic output; do not continue to database setup. The helper does not automatically undo a partial Composer operation.

The preview checks local files and prerequisites. It does not perform dependency resolution or prove database compatibility. The helper refuses an existing SWS/NeoWiki extension directory, package requirement or locked package. It does not inspect every possible historical database state.

This release supplies an extension, not a MediaWiki server or a Docker application. The generated source archive is for manual/development use; GitHub's automatic source ZIP alone does not install PHP dependencies.

[Next: configure LocalSettings.php](configuration.md)
