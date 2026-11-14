# MediaWiki MCP 0.21.0 – Patch für SWS 0.5.0

Ergänzt den offiziellen MediaWiki-MCP-Server 0.21.0 um SemanticWikiSchema und SchemaBaseBridge. Die ursprünglichen Werkzeuge einschließlich NeoWiki bleiben erhalten und werden nach der jeweils installierten Wiki-Erweiterung freigeschaltet.

## Voraussetzungen

- MediaWiki-MCP-Server **0.21.0**; keine unbekannten Änderungen in den betroffenen Dateien.
- Node.js ab 22.19; geprüft mit 24.22.1. Python ab 3.9 für den Laufzeit-Installer.
- SWS 0.5.0 mit der geprüften Schnittstelle. Der geprüfte Entwicklungsstand und das Live-Wiki bezeichnen sich intern als **0.5.00-alpha.8**. Dieses Paket ändert keine SWS-Versionsdateien. Für einen später abweichenden SWS-Quellcode sind die Funktionstests zu wiederholen.
- Für die vollständige Datenansicht zusätzlich SchemaBaseBridge.

## Inhalt

- `anwenden.py`: Vorprüfung, Installation mit Sicherung und Wiederherstellung.
- `Austauschdateien/` und `DATEIVERGLEICH.json`: gebaute Laufzeitdateien und SHA-256-Vergleich gegen das offizielle npm-Paket 0.21.0.
- `MediaWiki-MCP-0.21.0-SWS-0.5.0-Quellcode.patch`: vollständiger Quellcode-Patch gegen den unveränderten offiziellen Tag v0.21.0, Commit `34d772b03afd4e4104cc0f5c8a1ce3db7c9e5ea4`.
- `Quellcode/`: geänderte und ergänzte Quelldateien einschließlich Tests.
- `Installation/`: npm-Projektdateien mit festgeschriebenen Abhängigkeiten für eine getrennte Installation.
- `TEST-RESULTS.json` und `Pruefungen/`: Prüfergebnisse.
- `LICENSE-UPSTREAM`: MIT-Lizenz des ursprünglichen Projekts. Die Code-Ergänzungen dieses Pakets werden ebenfalls unter dieser MIT-Lizenz bereitgestellt.

## Getrennte Neuinstallation

1. Den Ordner `Installation` in einen eigenen dauerhaften Programmordner kopieren.
2. Dort die Abhängigkeiten installieren:

   ```sh
   npm ci --ignore-scripts
   ```

3. Die eigene `config.json` dort einrichten oder aus der bisherigen Installation übernehmen. Bestehende OAuth-Anmeldedaten bleiben an ihrem bisherigen Speicherort; sie gehören nicht in ein öffentliches Paket.
4. Den folgenden Laufzeit-Patch auf `node_modules/@professional-wiki/mediawiki-mcp-server` anwenden.
5. Die KI-App auf diesen Server verweisen oder nach erfolgreichen Tests den bisherigen Programmpfad auf die vorbereitete Installation umstellen. Bei einer Umstellung die alte Installation vollständig aufbewahren.

## Laufzeit-Patch

MCP-Verbindung während der Installation beenden. Zuerst nur prüfen:

```sh
python3 anwenden.py /pfad/zu/node_modules/@professional-wiki/mediawiki-mcp-server
```

Nach erfolgreicher Vorprüfung installieren:

```sh
python3 anwenden.py /pfad/zu/node_modules/@professional-wiki/mediawiki-mcp-server --apply
```

Der Installer prüft alle betroffenen Dateien vor der ersten Änderung, sichert vorhandene Dateien und verweigert unbekannte Ausgangsstände. Eine erneut angewandte identische Fassung verändert nichts. Bei einem Schreibfehler versucht er, alle bereits geänderten Dateien automatisch wiederherzustellen. Konfiguration, Anmeldedaten und die offizielle Paketversionsnummer bleiben unverändert. Der SWS-Patch wird separat in `sws-patch.json` ausgewiesen.

Anschließend die MCP-Verbindung neu starten, damit die KI-App den Werkzeugkatalog neu lädt. Eine bereits laufende Verbindung verwendet weiterhin den zuvor geladenen Prozess.

## Rücksetzen

Der Installer nennt nach der Anwendung den Sicherungsordner. Bei beendeter MCP-Verbindung:

```sh
python3 anwenden.py /pfad/zu/node_modules/@professional-wiki/mediawiki-mcp-server --restore /pfad/zum/MCP-SWS-backup-ORDNER
```

Spätere unbekannte Dateiänderungen verhindern eine Wiederherstellung. Bei einer vollständigen Migration von 0.20.x zusätzlich den bisherigen Programmordner zurücksetzen; der Laufzeit-Rücksetzungsbefehl allein setzt nur den Patch innerhalb von 0.21.0 zurück.

Eine erneute npm-Installation kann die gepatchten Paketdateien ersetzen. Danach die Vorprüfung ausführen und den Patch gegebenenfalls erneut anwenden.

## Quellcode verwenden

Im unveränderten Quellcode von v0.21.0:

```sh
git apply --check /pfad/zu/MediaWiki-MCP-0.21.0-SWS-0.5.0-Quellcode.patch
git apply /pfad/zu/MediaWiki-MCP-0.21.0-SWS-0.5.0-Quellcode.patch
npm ci
npm run preflight
npm run check:mcp
```

Der Quellcode-Patch benötigt keine früheren SWS-/NeoWikiBase-Patches. Die Laufzeitdateien dieses Pakets stammen aus diesem zusammengeführten Quellcode. Die zusätzliche Laufzeitmarkierung `sws-patch.json` ist im Ordner `Austauschdateien` enthalten.

## Funktionen und Verhalten

Die zwölf SWS-Werkzeuge unterstützen Schema-Lesen, Subject-Suche und -Lesen, Validierung, Kennungsreservierung, Erstellen, Ersetzen, Löschen, Hauptsubject-Zuordnung und Cypher. `swsbase-get-data-view` liefert die vollständige SchemaBaseBridge-Datenansicht.

- Feldanmerkungen stehen getrennt unter `fieldNotes`. Beim Ersetzen bleiben ausgelassene Anmerkungen erhalten; eine übergebene Map ersetzt alle Anmerkungen, `{}` leert sie.
- Eigene mehrsprachige Identität verwendet `identityLanguage`, `labels` und `descriptions`. Beim Erstellen werden fehlende Angaben mit der Wiki-Sprache ergänzt. Bei einer Änderung ergänzt ein aktueller Lesezugriff ausgelassene Identitätsangaben. Ein Fehler bei diesem Lesezugriff verhindert das Schreiben.
- `sws-get-subject(latest=true)` liest die aktuell bearbeitbare Revision. `fieldKeyVersion` kann beim Ersetzen zurückgegeben werden, um Änderungen von Feldschlüsseln zu berücksichtigen.
- Kennungsreservierungen benötigen Schreibrechte und ein CSRF-Token. In einer als schreibgeschützt konfigurierten Verbindung sind sie wie die übrigen Schreibwerkzeuge gesperrt.
- Private OAuth-Wikis verwenden ihre Anmeldung bereits bei der Erkennung der Erweiterungen.
- Cypher benötigt einen Neo4j-Speicher im Wiki. Das geprüfte Wiki besitzt keinen entsprechenden Endpunkt; dort wurde die erwartete Nichtverfügbarkeit festgestellt. Cypher-Aufrufe und Fehlerbehandlung sind durch automatisierte Tests abgedeckt.

## Prüfung

2.631 automatisierte Tests in 182 Dateien bestanden. Der vollständige Projektprüflauf einschließlich Build, Typprüfung, Formatprüfung, Manifestprüfung und Paketbau bestand. Der MCP-Protokollcheck meldete keine fehlgeschlagenen Prüfungen; nicht anwendbare bzw. vom Prüfprofil nicht ausgeführte Fälle sind im Protokoll ausgewiesen.

Neun Installer-Prüfungen bestanden, einschließlich Wiederholbarkeit, Rücksetzen, Ablehnung fremder Änderungen/beschädigter Dateien/symbolischer Links und automatischer Wiederherstellung nach einem simulierten Schreibfehler. Der Quellcode-Patch ließ sich auf unverändertes v0.21.0 anwenden.

22 Schritte des Live-Tests liefen erfolgreich mit einer frisch gestarteten Verbindung gegen das private Wiki. Sie umfassten Anlegen und Ändern, mehrsprachige Identität, Feldanmerkungen, abhängige Subjects, Datenansicht und Löschen. Die gesonderte Schreibschutzprüfung bestand. Testseiten und Subjects wurden entfernt; ordnungsgemäß reservierte Kennungen werden nicht wiederverwendet. MediaWiki bewahrt gelöschte Seitenrevisionen gemäß seinem Löschverfahren auf.
