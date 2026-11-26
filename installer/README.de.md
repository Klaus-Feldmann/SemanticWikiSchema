# Semantic WikiSchema 0.7.0.3 – Erstinstallation

Für ein bereits eingerichtetes MediaWiki, in dem SWS erstmals installiert wird. Benötigt werden PHP 8.3+, Composer 2 und Python 3.9+. Geprüfte Umgebung: MediaWiki 1.47.0-wmf.24 mit Legacy-Parser.

Im entpackten Installerordner ausführen:

```sh
python3 install.py --mediawiki-root /pfad/zu/mediawiki --dry-run
python3 install.py --mediawiki-root /pfad/zu/mediawiki --apply
```

Das MediaWiki-Installationskonto verwenden. Bei Bedarf `--php /pfad/zu/php` und `--composer /pfad/zu/composer` ergänzen. Der Helfer sichert betroffene Composer-Dateien außerhalb des Webverzeichnisses und installiert das festgelegte Paket. Die Aktivierung, LocalSettings.php, Datenbankaktualisierung und Jobs werden anschließend gemäß [Installationsanleitung](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.7.0.3/docs/operations/install/de/README.md) eingerichtet.

[Funktionen, Einstellungen und KI-Unterstützung](https://github.com/Klaus-Feldmann/SemanticWikiSchema/blob/v0.7.0.3/docs/README.md) · [English](README.md).

Bei vorhandener Installation oder besonderem Umstellungsbedarf: [info@aufeinwort.info](mailto:info@aufeinwort.info). Der Erstinstallationshelfer ersetzt keine bestehende Installation.

Softwaredesign: Klaus Feldmann / AufEinWort. Programmierung: ChatGPT/Sophos. GPL-2.0-or-later; Mitwirkenden- und Urheberangaben bleiben im Erweiterungspaket erhalten.
