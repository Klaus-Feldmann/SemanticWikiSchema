# Prüfung 0.1.22

- Die vier Tabellen verwenden sws_-Namen; ihre Spalten und zugehörigen Indizes verwenden swsr_, swss_, swssp_ und swsrr_. Verweise in PHP, abstrakten Schemas und MySQL-/SQLite-/PostgreSQL-DDL wurden abgestimmt geändert.
- 542 PHP-Tests mit 917 Assertions erfolgreich. Bestehende Seiten-/Job-/Export-/Registrierungsverträge und Lua-Prüfungen erfolgreich.
- 110 zusätzliche Migrationsprüfungen auf echten SQLite-Testdatenbanken erfolgreich: Bericht ohne Schreibzugriffe, Tabellen/Spalten/Indizes, Tabellenprefixe, binäre Werte, Subjekt-IDs, historische und gelöschte Slotverweise, unveränderte Inhaltsblobs, Abbruch bei Zielkonflikten/geändertem Bestand, privates Journal und Rückweg.
- 8 zusätzliche MySQL/MariaDB-Adapterprüfungen mit aufgezeichneten SQL-Befehlen erfolgreich, einschließlich Tabellenprefix, RENAME TABLE/COLUMN/INDEX, Metadatenupdate und Versionsgrenzen. Dies ist kein echter MariaDB-Speicherlauf.
- Die beiden MediaWiki-Wartungsskripte wurden über den vorhandenen MediaWiki-1.47-Wartungsrunner mit --help geladen. 1156 PHP-Dateien erfolgreich auf Syntax geprüft.
- Oberflächen-/Graphcode und Assets sind gegenüber der geprüften 0.1.21 funktional unverändert. Deren 3747 Oberflächentests und Graphprüfungen wurden deshalb nicht nochmals vollständig wiederholt.
- Die MCP-Anpassung wird unverändert aus 0.1.21 mitgeführt. Deren vollständige MCP-Suite konnte wegen fehlender Abhängigkeiten/nicht erreichbarer Registry nicht wiederholt werden; die dort dokumentierten Laufzeitpatch-/Syntax-/Requester-Prüfungen sind die vorhandene Evidenz.

Live bestätigt am 08.11.2026: M4-Migrationsjournal mit allen 42 Operationen, pending=null, state=complete. Browser-Datenanzeige und Bearbeitung durch den Anwender bestätigt. MCP-Patch auf dem konfigurierten Server 0.20.0 installiert, 20 JavaScript-Dateien auf Syntax geprüft; nach Neustart SWS-Schemaliste, Schema Person, Goethe-Subjekt, Gödel mit abhängigem Subjekt Ehrungen, Suche und vollständige Datenansicht von Cusanus erfolgreich gelesen. Die gefilterte Goethe-Datenansicht liefert P31 und historische Datums-/Kalenderangaben. Eigene und externe Werte bleiben unterscheidbar.

Noch nicht neu verifiziert: frische Composer-Installation 0.1.22, JSON-/Turtle-/TriG-Exportdownloads dieser Version, MCP-Schreiboperationen und Migration beliebiger offizieller NeoWiki-Versionen. Ein Refresh zu Werk:De mathematica perfectione meldete retained; der Anwender ordnet dies einem gelöschten Schema zu. Dieser Einzelfall ist nicht als erfolgreich verarbeiteter Job ausgewiesen.

Die Slotreihenfolge bleibt unverändert; zusätzliche Slots, die durch die Umbenennung die Reihenfolge einer betroffenen historischen Revision verändern würden, führen bereits im lesenden Bericht zum Abbruch. Revisionsprüfsummen werden nicht umgeschrieben.

M4-Migrationsjournal nachgereicht: alle 42 Operationen abgeschlossen, pending=null, state=complete. Die produktive Datenmigration ist damit bestätigt; Codewechsel und anschließende Funktionstests stehen noch aus. Wartungsskripte berücksichtigen ReadOnly mittels prozesslokaler Konfigurationsüberschreibung.
