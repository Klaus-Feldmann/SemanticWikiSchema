# D6 abgeschlossen – SWS 0.5.00-alpha.6

Grundlage: vollständiger D5-Stand 0.5.00-alpha.5. Ergebnis ist ein privater Teststand; keine Veröffentlichung auf GitHub und keine Änderung der Live-Installation.

## Ergebnis

- Mehrsprachige Feldlabels können im Schemaeditor je Sprache gepflegt werden. Doppelte Sprachangaben verhindern das Speichern, bis sie berichtigt sind.
- Schemaanzeige und Feldliste zeigen das sprachabhängige Label zusammen mit der technischen Kennung. Eingabemaske, Datenanzeige und Graph verwenden die passende Beschriftung.
- Anzeigereihenfolge: Benutzersprache, MediaWiki-Rückfallsprachen, Wiki-Inhaltssprache, technische Feldkennung. Der Graph verwendet seine gewählte Datensprache als Ausgangspunkt.
- Technische Kennungen bleiben manuell vergeben. Verschieben oder Übersetzen eines Feldes nummeriert nichts um. `{{#sws_value: E3 | F4 }}` bleibt unabhängig vom sichtbaren Label gültig. Abfragen verwenden weiterhin den technischen Schlüssel.
- Werte, Quellenzuordnungen, konfigurierte Linkziele und SMW-Namen bleiben erhalten. RDF ergänzt sprachmarkierte Labels am bestehenden Feldprädikat. Bestehende Felder ohne Labels behalten ihre bisherige Anzeige.
- Schema-JSON, Lua-Schemaausgabe, Datenansicht und Graph-Metadaten transportieren die Labels. Die neuen de/en-Nachrichten sind über MediaWiki-Nachrichten anpassbar.

## Prüfung

| Prüfung | Ergebnis |
|---|---|
| PHP-Tests | 554 Tests, 943 Assertions bestanden |
| Isolierte Verarbeitungstests | 64 Skripte bestanden |
| Frontend | 3.847 Tests in 246 Dateien bestanden |
| TypeScript und Frontend-Build | bestanden |
| Graph | 75 Tests, Build, Bundle-Vergleich und DOM-Prüfungen bestanden |
| Installationshelfer | 13 Tests bestanden |
| Geänderte PHP-Dateien | Syntaxprüfung bestanden |
| ZIP | CRC, Dateiliste und SHA256 aller Dateien geprüft |

Die Prüfungen decken unter anderem Label-Speicherung, Sprachwechsel und Rückfall, doppelte Spracheingaben, Typwechsel bei unverändertem F4, sichere Textanzeige, unveränderte Linkziele sowie RDF-/SMW-Identitäten ab. Dies sind lokale Prüfungen mit kontrollierten Testumgebungen; ein tatsächlicher MediaWiki-Speicher- und Joblauf wurde nicht ausgeführt.

## Bereitstellung und Praxistest

Das ZIP enthält den vollständigen Ordner `SemanticWikiSchema` einschließlich gebauter Browserdateien. Lokale Konfiguration und vorhandene Laufzeitabhängigkeiten wie in der mitgelieferten Anleitung übernehmen. Die Composer-Abhängigkeiten haben sich nicht geändert. Lokalisierungscache erneuern und Browserressourcen neu laden. Keine SQL-Änderung, Identitätsmigration oder Suchindex-Neuerstellung erforderlich.

Für den gezielten Test in einem Schema bei einem Feld deutsche und englische Labels ergänzen. Schema, Datenmaske und Datenanzeige mit `uselang=de` und `uselang=en` vergleichen; im Graph die Datensprache wechseln. Danach das Feld verschieben und das Label ändern: Abfragen mit derselben Kennung müssen denselben Wert liefern. Für neue RDF-Labels die betroffenen Seiten neu verarbeiten; vorhandene Entitätswerte müssen nicht migriert werden.

Die ausführliche Anleitung liegt im Paket unter `docs/operations/test-candidate-de.md`. D7 ist noch nicht ausgeführt.
