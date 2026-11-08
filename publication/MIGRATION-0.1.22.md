# Wechsel mit erhaltenen Daten – 0.1.22

## Ziel und Umfang

Diese Fassung ergänzt die technische Umbenennung um eigene Spalten- und Indexpräfixe:

| Tabelle | Bisherige Spalten/Indexpräfixe | Neue Präfixe |
|---|---|---|
| neowiki_relation → sws_relation | nwr_ | swsr_ |
| neowiki_subject → sws_subject | nws_ | swss_ |
| neowiki_subject_page → sws_subject_page | nwsp_ | swssp_ |
| neowiki_rebuild_runs → sws_rebuild_runs | nwrr_ | swsrr_ |

Der vorbereitete Wechsel arbeitet durch gezielte Umbenennungen. Inhalte, Subjekt- und Beziehungs-IDs, historische Slotverweise und numerische Inhaltsmodell-IDs bleiben erhalten. Es erfolgt kein globaler Textersatz über Forschungsinhalte. Auch ein Tabellenwert wie aewi-neowiki bleibt ein unveränderter Wert.

Die Migration ist für die vorhandene Vor-Umbenennungsstruktur vorbereitet. Der Wechsel der bereitgestellten, angepassten 0.1.20 auf 0.1.22 wurde auf AufEinWort erfolgreich durchgeführt. Die allgemeine Übertragbarkeit auf andere NeoWiki-Versionen ist dadurch noch nicht nachgewiesen.

## Zuerst nur den Bestand prüfen

Das neue Paket separat ablegen. Die laufende Extension 0.1.20 und ihre Einstellungen noch nicht ersetzen. Vom MediaWiki-Hauptverzeichnis aus MigrateIdentifiers.php per absolutem Pfad aufrufen, ohne --apply.

Der Standardlauf liest Tabellen, Spalten, Indizes und die betroffenen MediaWiki-Zuordnungen. Er erzeugt PLAN.json und BESTAND.json mit privaten Dateirechten außerhalb des Webverzeichnisses. Er verändert keine Datenbankeinträge, Seiten oder Jobs. Die Berichte enthalten interne Metadaten und gehören nicht in ein öffentlich erreichbares Webverzeichnis.

Vor einer Anwendung den echten Bericht prüfen. Bereits bestehende Zieltabellen, Ziel-Slotnamen oder Ziel-Inhaltsmodellnamen führen zum Abbruch; es findet kein unbeaufsichtigtes Zusammenführen statt.

## Geplanter anschließender Ablauf

1. Vollständige, unabhängige Datenbank-/Dateisicherung und bestehende Graphspeichersicherung sicherstellen. Der Migrationsjournal ersetzt dieses Backup nicht.
2. Alte SWS/NeoWiki-Jobs vollständig abarbeiten. Danach automatischen und externen Joblauf anhalten und Webzugriff während der Umstellung unterbrechen. wgReadOnly setzen; es verhindert Änderungen, sperrt jedoch allein keine Lesezugriffe.
3. Die normale Konfigurationsseite MediaWiki:NeoWiki und aktive Lua-Aufrufe für den Wechsel vorbereiten. Die Einstellungen müssen künftig auf MediaWiki:SWS verfügbar sein. Das mitgelieferte Modul:WikiSchema verwendet mw.sws; Wikidata/Wikibase-Module bleiben unverändert. Diese Wiki-Änderungen erfolgen über MediaWiki, nicht durch Ersetzung von Artikeltext in SQL.
4. Den geprüften PLAN.json mit MigrateIdentifiers.php --apply, --plan, --jobs-stopped und --backup-confirmed anwenden. Dabei ist die bisherige Extension noch aktiv. Anwendung und normalisierte Daten-/ID-Abdrücke werden in VORGANG.json dokumentiert. Tabellenprefixe der MediaWiki-Installation werden berücksichtigt. Unterstützter Live-Adapter: MySQL >=8.0 oder MariaDB >=10.5.3.
5. Unter weiter angehaltenem Web-/Jobzugriff den vollständigen Extension-Ordner gegen 0.1.22 tauschen. wgNeoWiki-Einstellungsnamen auf wgSWS umstellen; Produkt-/Lade-/Installationsnamen SemanticWikiSchema und SchemaBaseBridge bleiben gleich. Graph-URLs, RDF-Basis und tatsächliche Dienstnamen nicht versehentlich ändern. Die Browser-Graphkonfiguration verwendet SWSGraphConfig.
6. Composer-Autoload und Lokalisierungscache erneuern, PHP-Prozesse aktualisieren und den passenden MediaWiki-Updateablauf ausführen. Die gesonderte MCP-Anpassung und der Client-Neustart werden mit dem API-Wechsel koordiniert.
7. FinalizeIdentifiers.php unter der neuen Extension mit --journal ausführen. Es leert gezielt die Caches der ermittelten Seiten und reiht normale SWS-Verarbeitungsjobs für Seiten mit Subjektslot ein. Es erstellt keine Artikelrevisionen und startet keinen Gesamtwiki-Rebuild.
8. Joblauf wieder aufnehmen und an bestehenden/neuen Datensätzen, älteren Versionen, abhängigen Subjekten, SMW, Graph und Exporten prüfen. Wartungszustand erst nach Bestätigung beenden.

Die Befehle für die schreibenden Phasen werden nach Prüfung des tatsächlichen Bestandsberichts mit dessen realen Dateipfaden zusammengestellt. Die neue Extension vor diesem koordinierten Wechsel nicht einfach über 0.1.20 installieren.

## Sicherung und Rückweg

PLAN.json beschreibt die Operationen; BESTAND.json enthält den lesend erfassten Ausgangszustand der betroffenen Daten/Referenzen. VORGANG.json hält die geprüften Ausgangsdaten und jeden abgeschlossenen beziehungsweise eventuell unklaren Schritt fest. Dateien erhalten Modus 0600, neue Berichtsordner Modus 0700.

Eine vollständig abgeschlossene Migration kann mit --rollback auf Basis ihres echten VORGANG.json zurückgenommen werden, solange sich die geschützten betroffenen Daten nicht verändert haben. Cache-Zeitstempel sind hiervon ausgenommen. Code, Konfigurationsseite und Einstellungen müssen passend zurückgestellt werden. Neue oder geänderte Datensätze führen zur Verweigerung des automatischen Rückwegs. Abgebrochene oder bei Verbindungsabbruch unklare DDL-Schritte werden nicht erraten; Journal und vollständiges Backup bleiben für geprüfte Wiederherstellung erhalten.

MySQL-DDL ist nicht als eine gemeinsame rückrollbare Transaktion dargestellt. Die Migration löscht keine alten Inhalte, führt aber absichtlich Tabellen-/Spalten-/Metadatenumbenennungen aus. Deshalb bleiben Vorprüfung, Wartungsfenster und unabhängiges Backup erforderlich.

## AufEinWort: bestätigter Übergang

Der Live-Bericht enthielt 53 Subjekte, 11 Beziehungen, 53 Seitenzuordnungen und 2 Rebuild-Protokolle. Alle 42 Migrationsoperationen wurden mit geprüftem Datenvergleich abgeschlossen. Die Abschlussverarbeitung leerte 51 Seitencaches und reihte 43 normale SWS-Jobs ein. Modul:WikiSchema wurde durch gezielten Ersatz von mw.neowiki zu mw.sws angepasst; die Konfigurationsseite wurde mit unverändertem Inhalt nach MediaWiki:SWS verschoben. Browserbearbeitung und lesende MCP-Abfragen sind bestätigt.

Aufgegebene Jobs werden von der Vorprüfung ebenfalls erfasst: eine Meldung »Job queue is empty« beweist nur, dass kein Job ausführbar ist. AufEinWort enthielt 228 aufgegebene Altjobs für 20 vom Plan abgedeckte Seiten. Sie wurden vor ihrer gezielten Entfernung vollständig gesichert und gegen den unveränderten Bestand geprüft. Dies ist keine Aufforderung, andere Warteschlangen pauschal zu löschen. Erst Bestand, Ursachen und Ersatzverarbeitung prüfen.

Die schreibenden Wartungsskripte verlangen weiterhin einen gesetzten ReadOnly-Wartungszustand, heben dessen Konfiguration aber nur in ihrem eigenen CLI-Prozess vor der Dienstinitialisierung auf. LocalSettings.php und Webzugriffe bleiben davon unverändert. Zum regulären Abarbeiten der SWS-Jobs muss die Schreibsperre dagegen aufgehoben werden; Webzugriffe sind während des Übergangs gesondert zu unterbinden.
