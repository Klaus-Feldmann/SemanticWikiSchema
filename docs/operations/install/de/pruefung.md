# Datenbank, Jobs und Funktionsprüfung

Für den Betreiber nach der [Aktivierung](konfiguration.md). Im MediaWiki-Hauptverzeichnis ausführen:

```sh
php maintenance/run.php update --quick
php maintenance/run.php rebuildLocalisationCache --force
```

Den für das Wiki verwendeten PHP-Pfad und gegebenenfalls `--conf /pfad/zu/LocalSettings.php` verwenden. Bei einem Fehler stoppen. Für die Erstinstallation wird kein Identitäts-Migrationsbefehl benötigt.

Den regulären Jobrunner aktiv halten. Ein manueller dauerhafter Lauf lautet:

```sh
php maintenance/run.php runJobs --wait
```

Für den Dauerbetrieb den vorhandenen Dienstmanager oder die geplante Jobausführung verwenden. Nach Wartungsarbeiten wieder einschalten; Quellenabrufe und schemaabhängige Aktualisierungen benötigen die Jobverarbeitung.

Anschließend prüfen:

1. `Spezial:Version` zeigt SemanticWikiSchema 0.5.0 und SchemaBaseBridge 0.21.1.
2. `Spezial:Schemata` lädt die Schemaliste.
3. Ein Schema mit einem Textfeld anlegen: Die Seite erhält eine S-ID; das Label lässt sich ändern.
4. Auf einer Testseite unter „Daten“ eine Entität anlegen und einen Wert speichern. Nach dem Neuladen bleiben E-ID und Wert erhalten.
5. Das Schema-Label ändern und die Datenansicht erneut laden: Das geänderte Label wird ohne zweite Entität angezeigt.
6. Bei eingerichteter Quellen-/SMW-Anbindung einen Quellenwert und die daraus erzeugte Annotation nach den Jobs prüfen.

Kurze Entity-Links und Graph Explorer benötigen die [zusätzliche Serveranbindung](erweiterungen.md).

## Feldkennungen, Sprachen und Abfragen

Im neuen Testschema eine Kennung wie `N1` sowie deutsche und englische Feldbenennungen vergeben. Einen Entitätswert speichern und mit `{{#sws_value: E3 | N1 }}` vergleichen; die tatsächliche E-ID einsetzen. Bei einem Wechsel der Oberflächensprache ändern sich die Labels, nicht die Kennung. Für einen als Seitentitel geeigneten Wert auch `[[{{#sws_value: E3 | N1 }}|Linktext]]` prüfen.

Die Kennung dieses Testfeldes auf `Z1` ändern, Schema sichern und die Jobs abarbeiten lassen. Nach erneutem Aufruf müssen Wert und Anmerkung unter `Z1` erhalten sein. Ein erneuter Verarbeitungslauf darf die Übernahme nicht wiederholen. Abfragen mit `N1` sind ausdrücklich anzupassen; Artikeltext wird nicht umgeschrieben.

Nach der optionalen SPARQL-Einrichtung auch die Feldlabels über „Data Language“ im Graph Explorer umschalten.
