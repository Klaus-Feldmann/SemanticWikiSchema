# Installer ausführen

Für den Betreiber, der Dateien und PHP-Abhängigkeiten installiert. Zuerst die [Voraussetzungen](voraussetzungen.md) prüfen.

**SemanticWikiSchema-0.5.0-installer.zip** von der [GitHub-Veröffentlichung](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/v0.5.0) herunterladen, außerhalb des Webverzeichnisses entpacken und im Terminal in diesen Ordner wechseln.

Zunächst nur prüfen; den MediaWiki-Pfad anpassen:

```sh
python3 install.py --mediawiki-root /pfad/zu/mediawiki --dry-run
```

Danach installieren:

```sh
python3 install.py --mediawiki-root /pfad/zu/mediawiki --apply
```

Bei Bedarf `--php /pfad/zu/php` und `--composer /pfad/zu/composer` ergänzen. `--backup-dir /privater/pfad` bestimmt ein Sicherungsverzeichnis außerhalb des MediaWiki-Webverzeichnisses. Ohne Angabe werden die Sicherungen unter `.sws-installer-backups` im persönlichen Verzeichnis des Installationskontos abgelegt.

Der Installer ergänzt das festgelegte Paket `aufeinwort/semantic-wikischema:0.5.0` samt GitHub-Repository in `composer.local.json`, erlaubt die benötigten Composer-Plugins in der Hauptkonfiguration und führt ein gezieltes Composer-Update dieses Pakets mit seinen Abhängigkeiten aus. Andere Einstellungen bleiben erhalten. Ausdrücklich gesperrte benötigte Plugins führen zum Abbruch. Die betroffenen Composer-Dateien und eine vorhandene Sperrdatei werden vorher privat gesichert.

Das Ziel ist `extensions/SemanticWikiSchema`. Vorhandene SWS-/NeoWiki-Verzeichnisse, Paketanforderungen oder entsprechende installierte Pakete führen zum Abbruch. Die Vorschau liest nicht jeden denkbaren historischen Datenbankzustand aus und prüft noch nicht die Auflösbarkeit sämtlicher Composer-Abhängigkeiten.

Bei einem Composer-Fehler hier stoppen. Der Installer führt keinen automatischen Rückbau durch. Er verändert weder LocalSettings.php noch Wikiseiten und führt keine Migration aus. Das Paket installiert eine Erweiterung in MediaWiki, keinen MediaWiki-Server und keine Docker-Anwendung.

[Weiter: LocalSettings.php](konfiguration.md)
