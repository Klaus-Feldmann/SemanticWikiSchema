# Upgrade SWS 0.5.x → 0.6.0

For administrators of an existing SWS installation. [Deutsch](#deutsch). The supplied folder upgrader recognizes the checked 0.5.0 and 0.5.02 distributions; modified code or other development builds require manual review. It never upgrades NeoWiki.

## Before upgrading

Back up the database, uploaded files, LocalSettings and complete extension directory. Stop persistent job workers and PHP processes before replacing code, then restart them afterward: SWS classes now use `AEWI\SWS`. Existing E/S identifiers, schemas and field values remain in their existing format. Custom PHP code referencing `ProfessionalWiki\SWS` must be adapted. Do not run old and new worker code together.

Seven default automatic SMW property names can be set through content-language MediaWiki messages. The German entity-assignment default is now `WikiSchema-Entität`. Explicit mappings retain priority. Choose any naming overrides before rebuilding derived data; see [SMW names](#smw-names).

## Folder installation

Extract the **upgrade ZIP**, which contains the source archive and helper. Run the preview first:

```sh
python3 upgrade.py --extension /path/to/mediawiki/extensions/SemanticWikiSchema
```

After stopping PHP and job workers:

```sh
python3 upgrade.py --extension /path/to/mediawiki/extensions/SemanticWikiSchema --backup-dir /private/backups/sws --apply --processes-stopped
```

The helper validates known runtime files and the complete source manifest, saves the old directory outside the web root and swaps in the prepared folder. Existing runtime dependencies, additional files and `graph-explorer/config.js` are retained. Unknown code, symbolic links and Composer-managed installations are refused. It does not change LocalSettings or the database. Keep your existing PHP dependencies installed; if the extension has its own Composer vendor directory, regenerate its autoloader with `composer dump-autoload --no-dev` in that extension directory before restarting.

## Composer-managed installation

Use Composer rather than the folder helper. Back up `composer.json`, `composer.local.json` and `composer.lock`; change the existing SWS requirement to `0.6.0` in the file containing it. From the MediaWiki directory, with the services stopped:

```sh
composer update aufeinwort/semantic-wikischema --with-dependencies --no-dev --prefer-dist --no-scripts
```

Inspect the resulting lock-file changes. This refreshes the PHP autoload definitions as part of the package update.

## After replacing code

Run with the PHP executable used by your wiki, from the MediaWiki directory:

```sh
php maintenance/run.php update --quick
php maintenance/run.php rebuildLocalisationCache --force
```

Restart PHP/job services. Test one existing schema and entity, an edit/save and the entity links (`action=entities`; old `action=subjects` links remain supported). Check Special:Version and your semantic output. The maintenance commands above are the general upgrade checks; this release does not require an E/S identity or stored-field migration.

Reprocess existing SWS pages **once after choosing the SMW names** if you want existing derived annotations to use the new defaults. The full `rebuildData` operation can enqueue substantial downstream work; schedule it for your installation and let jobs drain. Updating the localization cache alone does not rewrite existing annotations. If projections were already rebuilt during alpha/beta testing, do not repeat that work merely for the final version number. Description-based search and maintenance categories become current through normal page/index processing; indexes can lag.

## SMW names

Message overrides use the wiki content language and are configuration, not schema field names:

| MediaWiki message | German default |
| --- | --- |
| `sws-smw-property-url` | Quellen-URL |
| `sws-smw-property-label` | Quellentitel |
| `sws-smw-property-caption` | Quellenbezeichnung |
| `sws-smw-property-caption-page` | Quellenbezeichnungsseite |
| `sws-smw-property-field` | WikiSchema-Feld |
| `sws-smw-property-entity` | WikiSchema-Entität |
| `sws-smw-property-description` | Kurzbeschreibung |

Create an override as `MediaWiki:Sws-smw-property-entity`, for example, containing the chosen property name. An explicit `$wgWikiSchemaSmwMapping` configuration keeps priority. Old property pages, historical revisions and external links are not renamed by a rebuild; inspect downstream queries when choosing a different name.

## Rollback

Stop PHP/job processes. Preview a folder rollback, then apply it:

```sh
python3 upgrade.py --extension /path/to/mediawiki/extensions/SemanticWikiSchema --rollback /private/backups/sws/SAVED-DIRECTORY
python3 upgrade.py --extension /path/to/mediawiki/extensions/SemanticWikiSchema --rollback /private/backups/sws/SAVED-DIRECTORY --apply --processes-stopped
```

Automatic rollback refuses changed installed files or a damaged backup. Composer installations restore the backed-up configuration/lock files and reinstall the previous locked dependencies. Restart services after restoring. File rollback does not reverse database edits or derived annotations made since upgrading; use your database backup or separately reprocess affected pages as appropriate.

## Deutsch

1. Datenbank, Uploads, LocalSettings und Erweiterungsordner sichern. Zuerst den tatsächlichen Installationsweg feststellen: Composer verwaltet SWS, wenn das Paket im zentralen `composer.lock` steht. Dann den oben beschriebenen Composer-Weg verwenden; der Ordner-Installer lehnt diesen Fall ab.
2. Vor dem Dateiaustausch PHP und dauerhafte Job-Prozesse anhalten. Der neue PHP-Namensraum heißt `AEWI\SWS`; eigene PHP-Anpassungen mit dem bisherigen Namensraum müssen angepasst werden. Neue und alte Prozesse dürfen nicht gleichzeitig arbeiten.
3. Beim Ordner-Upgrade zunächst die Vorschau ohne `--apply` ausführen. Nur bei erfolgreicher Prüfung den Aufruf mit `--backup-dir`, `--apply` und `--processes-stopped` verwenden. Der Sicherungsordner muss außerhalb des Webverzeichnisses liegen. Laufzeitabhängigkeiten und die vorhandene Graph-Explorer-Konfiguration bleiben erhalten.
4. Anschließend `update --quick` und `rebuildLocalisationCache --force` gemäß den Aufrufen oben ausführen, Dienste neu starten und an einer bestehenden Seite Lesen, Bearbeiten und Speichern testen. Eine Migration der gespeicherten Entitäts- oder Schema-IDs ist nicht erforderlich.
5. Gewünschte SMW-Namen vor einer einmaligen Aufbereitung vorhandener Projektionen festlegen. Der deutsche Standard `WikiSchema-Subjekt` wird zu `WikiSchema-Entität`. Eigene explizite Zuordnungen behalten Vorrang. Alte Eigenschaftsseiten und externe Abfragen werden nicht automatisch umbenannt. `rebuildData` kann viele Folgejobs erzeugen; erst nach der Einzelprüfung geplant ausführen. Bereits im Alpha-/Beta-Test aktualisierte Projektionen müssen nicht allein wegen der finalen Versionsnummer nochmals aufgebaut werden.
6. Die Wartungskategorie für fehlende Hauptentitätsbeschreibungen heißt standardmäßig `SWS Entitätsbeschreibung fehlt`. Sie ist über `MediaWiki:Sws-entity-description-missing-category` anpassbar; `-` deaktiviert sie. Kategorie- und Suchindexdaten können nachlaufen.

Die Rücknahme ersetzt die Dateien aus der Sicherung; sie nimmt zwischenzeitliche Datenänderungen oder SMW-Aufbereitungen nicht automatisch zurück.
