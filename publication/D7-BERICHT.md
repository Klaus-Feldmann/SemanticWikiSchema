# D7 abgeschlossen – SWS 0.5.00-alpha.7

D7 führt D1–D6 zum vollständigen privaten Teststand zusammen. Grundlage ist alpha.6; GitHub-Veröffentlichung und Änderungen an der Live-Installation wurden nicht ausgeführt.

## Ergebnis

Die gemeinsame Prüfung deckt Abfragewerte und deren Linkverwendung, schemaeigene F-Kennungen sowie mehrsprachige Feldlabels ab. Ein Schema wurde über die tatsächlichen PHP-/Frontend-Serializer gespeichert und wieder eingelesen, die Feldreihenfolge verändert und die Anzeige zwischen Deutsch und Englisch gewechselt. E-ID/F-Kennung, Wert, Datentyp und konfiguriertes Linkziel bleiben dabei erhalten. Gleiches F4 in einem anderen Schema bleibt unabhängig. Beschreibende technische Schlüssel bleiben nutzbar; automatische Nummerierung wird nicht eingeführt.

Bei der Abschlussprüfung wurde eine Graph-Integrationslücke behoben: Die vorhandene Auswahl „Data Language“ wird jetzt an die Abfrage der Feldpräsentation weitergegeben. Ein bereits geöffneter Knoten übernimmt die neue Beschriftung. Der Präsentationscache trennt die Sprachen; verspätete Antworten der vorherigen Sprache können die aktuelle Anzeige nicht ersetzen. Das wurde auch am tatsächlich gebauten Graph-Bundle im DOM-Test geprüft.

## Prüfungen

| Bereich | Ergebnis |
|---|---|
| PHP | 554 Tests / 943 Assertions bestanden |
| Verarbeitung und Integration | 65 Skripte bestanden |
| Frontend | 3.849 Tests in 247 Dateien bestanden |
| TypeScript / Browser-Build | bestanden |
| Graph | 77 Tests, Build, Bundle-Vergleich und DOM-Prüfungen bestanden |
| Installationshelfer | 13 Tests bestanden |
| Paket | ZIP-CRC, Manifest und SHA256 aller Dateien geprüft |

Die zusätzlichen Integrationstests prüfen beide Quellenmodi. `store` liest lokal; `cache` behält sein vereinbartes Quellenverhalten. Eigene Werte haben Vorrang; eine ausdrücklich auf eigene Werte begrenzte Abfrage löst keinen zusätzlichen Quellenabruf aus.

## Installation und Praxistest

Das ZIP enthält den vollständigen Ordner `SemanticWikiSchema` mit fertigen Browserdateien. Vorhandene lokale Konfiguration und Laufzeitabhängigkeiten gemäß `docs/operations/test-candidate-de.md` erhalten, Lokalisierungscache erneuern, Browserressourcen neu laden und den Jobrunner wieder starten. `MediaWiki:SWS` bleibt für den Funktionstest unverändert.

Keine neue SQL-Struktur, Identitätsmigration oder Suchindex-Neuerstellung erforderlich. Bereits vor D5 vorhandene F-Felder benötigen die dokumentierte Neuprojektion ihrer RDF-/SMW-Kennungen. Für neue RDF-Labels betroffene Seiten neu verarbeiten; gespeicherte Entitätswerte werden nicht umgeschrieben.

Der Praxistest besteht aus einem geeigneten Testfeld mit deutschem und englischem Label, einem Wert und optionaler Anmerkung, einer Abfrage mit Link, anschließendem Verschieben und Umbenennen des Labels sowie dem Sprachwechsel in Oberfläche und Graph. Die Schrittfolge steht in der mitgelieferten Anleitung.

Alle genannten Ergebnisse stammen aus lokalen Tests und kontrollierten Testumgebungen. Ein echter MediaWiki-Speicher-/Joblauf und die Abnahme auf der installierten Umgebung stehen aus. D7 ist abgeschlossen; die Veröffentlichung bleibt zurückgestellt.
