# Lokale Integrationsprüfungen

Diese PHP-Dateien sind **keine Live-Testprogramme**. Sie legen Testseiten, Module und Vorlagen an, ändern Werte und löschen eine Testseite. Sie brechen außerhalb der Datenbank `semantic_test` mit Serveradresse `http://127.0.0.1:18747` ab. Sie werden nicht automatisch ausgeführt.

Die verwendete isolierte Umgebung: MediaWiki-Zweig `wmf/1.47.0-wmf.24`, PHP 8.3.36, SQLite, SMW 7.4.0, Scribunto aus demselben MediaWiki-Zweig, Legacy-Parser. Das Wiki wurde mit der Installationswartung lokal erstellt, Sprache Deutsch, Administrator `TestAdmin`; anonyme Leserechte dauerhaft `false`. Die eigene Arbeitsfassung liegt unter `extensions/SemanticWikiSchema`; die Bridge wird daraus geladen. SMW ist eingerichtet, die Tabellen wurden ausschließlich in dieser Testdatenbank angelegt.

Für die Wiederausführung muss diese isolierte Umgebung vorliegen. Beispiel aus deren MediaWiki-Verzeichnis (Pfad zum entpackten Paket anpassen):

```sh
printf '%s\n' "require '/PFAD/ZUM/REPOSITORY/publication-tests/private-processing/AdditionalCases.php';" | /opt/homebrew/opt/php@8.3/bin/php maintenance/run.php eval
```

Jede Testdatei in einem eigenen PHP-Prozess ausführen. `AdditionalCases.php` enthält über die Fixtures auch die Grundfälle. `Denied.php`, `IdentityPolicy.php` und `MoveDelete.php` ergänzen die betreffenden Fälle. `WithoutSMW.php` benötigt eine eigene Testkonfiguration ohne Laden von SMW; seine Bibliotheken dürfen weiterhin im Composer-Autoloader liegen. Auf Apple Silicon wurde für den lokalen Test Lua 5.1.5 ausschließlich unter `/private/tmp` gebaut, weil Scribuntos mitgelieferte macOS-Binärdatei x86_64 voraussetzt. Dies verändert keine Lua-Installation auf dem M4.

Zusätzlich wurden der echte `runJobs`-Aufruf, HTTP-Anmeldung/Purge sowie anonyme Seiten-, query-, ask-, SWS-Datenansicht- und REST-Exportabrufe geprüft. Der Webtestserver lauschte ausschließlich auf `127.0.0.1:18747` und wurde anschließend beendet. Die bestätigten Ergebnisse stehen in publication/VERIFICATION.md. Die private Live-Konfiguration, Passwörter, Datenbanken und vollständige HTML-Ausgaben werden nicht mitgeliefert.
