# Voraussetzungen

Für den Betreiber, der über die Installation dieser Fassung entscheidet.

- Manifest-Mindestversion: **MediaWiki 1.47.0-wmf.24**. Dies ist die im laufenden Wiki bestätigte Version; eine spätere 1.47-stable-Fassung ist damit noch nicht geprüft.
- **PHP ab 8.3**, bestätigt mit PHP 8.3.36, und der **klassische Parser**.
- Ein bereits eingerichtetes MediaWiki mit installierten PHP-Abhängigkeiten und **Composer 2**. Seine Composer-Konfiguration muss `composer.local.json` über `wikimedia/composer-merge-plugin` einlesen.
- **Python ab 3.9** für den Installer, Schreibrechte des Installationskontos auf Composer-Dateien und das Extension-Verzeichnis.
- Eine von der verwendeten MediaWiki-Version unterstützte Datenbank. SWS enthält SQL-Definitionen für MySQL/MariaDB, PostgreSQL und SQLite; dies ist keine vollständige Laufzeitprüfung aller drei Datenbankserver.
- **Vector 2022** als getestetes Skin. Die Eingabe ist für Desktop und Tablets ab 12 Zoll ausgelegt.

Die gebauten Browserdateien sind enthalten. **Node.js und npm sind auf dem Wikiserver nicht erforderlich.**

SMW, Wikibase, Scribunto und Fuseki sind optionale Anbindungen. Für die lokale Dateneingabe mit SWS müssen sie nicht sämtlich installiert werden. Erweiterte Suchvorschläge hängen vom vorhandenen Suchsystem und dessen Konfiguration ab.

[Vorschau und Erstinstallation](erstinstallation.md)
