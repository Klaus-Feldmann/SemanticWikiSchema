# D8 abgeschlossen – SWS 0.5.00-alpha.8

Grundlage ist die bereitgestellte Version `/Users/nikolaos/Documents/Codex/2026-11-14/SemanticWikiSchema 0.5.00-alpha.7`. Sie wurde unverändert belassen. Der neue Stand liegt in einer vollständigen Arbeitskopie und als Installations-ZIP vor. Die eigene Kürzung auf „Feldkennung“ beziehungsweise „field key“ und die letzten Nachrichtenkorrekturen sind übernommen. Keine Live-Änderung oder GitHub-Veröffentlichung.

## Automatische Schlüsselübernahme

Beim Ändern einer bestehenden Feldkennung im Schemaeditor merkt sich SWS den bisherigen und den neuen Schlüssel. Es werden nur die ursprüngliche und die zuletzt eingegebene Kennung gespeichert, nicht jeder Zwischenstand beim Tippen. Beim Speichern des Schemas starten die vorhandenen, in Seitengruppen abgearbeiteten Jobs.

Vor der normalen Seitenverarbeitung überträgt ein begrenzter interner Speicherweg die gespeicherten Aussagen einschließlich Relationen, die Feldanmerkungen und die Quellenstände aller gespeicherten Sprachen. Identitäten, Werte, Quellen-P-IDs, Belege und Artikeltext werden nicht als Zeichenfolgen ersetzt. Nur die zugehörigen Schlüssel und die Feldnamen in der Quellenmetadatenstruktur ändern sich. Pro Seite wird nur der SWS-Slot geschrieben; Seitenrevision und veröffentlichter Schemastand werden geprüft.

Das Schema speichert die aufeinanderfolgenden Zuordnungen als `fieldKeyChanges`, jede Entität den abgearbeiteten Stand als `fieldKeyVersion`. Eine verzögert verarbeitete Seite kann dadurch mehrere Änderungen nachholen. Wiederholte Jobs wenden abgeschlossene Änderungen nicht nochmals an; neue Entitäten beginnen beim aktuellen Stand. Eine spätere Wiederverwendung einer Kennung löst keine alte Migration erneut aus.

Kollisionen, Schreibschutz und konkurrierende Seitenänderungen verhindern die Übernahme statt Werte zu überschreiben. Entitätsbearbeitungen während einer ausstehenden Übernahme werden zurückgewiesen. Die Eingabemaske übermittelt außerdem ihren geöffneten Schlüsselstand, damit eine zwischenzeitliche Schemaänderung nicht unbemerkt gespeichert wird. Eine Schlüsselübernahme und eine Datentypänderung müssen getrennt gespeichert werden.

## Einheitliche Feldidentität

`F1`, `N1`, `Z1` und beschreibende Schlüssel werden gleich behandelt. Jedes Feld ist durch Schema und exakte Kennung bestimmt. Doppelte Kennungen werden unabhängig von ihrer Schreibweise im Editor und im rohen JSON abgewiesen. Es gibt keine automatische Nummerierung.

RDF verwendet beispielsweise `/schema-id/S1/field/N1`; die Kodierung unterscheidet auch Leerzeichen, Unterstriche, Unicode und Pfadsonderzeichen eindeutig. Graph und vollständige Exporte folgen derselben Regel. SMW verwendet standardmäßig `S1:N1`, einschließlich des konfigurierten Suffixes für Anmerkungen.

Ausdrückliche SMW-Zuordnungen haben Vorrang. Eine vorhandene Zuordnung am früheren Schlüssel wird über die Änderungshistorie weitergefunden; eine Zuordnung am aktuellen Schlüssel hat Vorrang. Es werden keine SMW-Attributseiten automatisch angelegt. Bei neuen Standardattributen bleibt die passende SMW-Datentypdefinition erforderlich.

## Prüfungen

| Bereich | Ergebnis |
|---|---|
| PHP-Domäne und Projektionen | 566 Tests / 979 Assertions bestanden |
| Anlegen und Ersetzen von Entitäten | 84 weitere Tests / 150 Assertions bestanden |
| Verarbeitung und Integration | 66 Skripte bestanden |
| Frontend | 3.852 Tests in 248 Dateien bestanden |
| TypeScript / Frontend-Build | bestanden |
| Graph | 78 Tests, Build, Bundle-Vergleich und DOM-Prüfungen bestanden |
| Installationshelfer | 13 Tests bestanden |
| Paketbau und Nachrichtenregistrierung | 2 Tests bestanden |
| Paket | ZIP-CRC, Manifest und SHA256 aller enthaltenen Dateien geprüft |

Die Prüfungen verwenden lokale kontrollierte Testumgebungen. Ein echter Schema-Speicherlauf, die Jobverarbeitung und RDF-/SMW-Abnahme auf dem installierten MediaWiki stehen aus.

## Installation und einmalige Neuberechnung

Den vollständigen Erweiterungsordner ersetzen und lokale Konfiguration sowie vorhandene Laufzeitabhängigkeiten erhalten. Aus dem MediaWiki-Stammverzeichnis:

```sh
touch extensions/SemanticWikiSchema/extension.json
php maintenance/run.php rebuildLocalisationCache --force
php maintenance/run.php SemanticWikiSchema:RebuildData
```

Browserressourcen neu laden und `php maintenance/run.php runJobs --wait` wieder laufen lassen. Der Rebuild erneuert die abgeleiteten Daten bestehender SWS-Seiten, insbesondere die bisherigen Standardkennungen benannter Felder. Es ist kein Suchindex-Neuaufbau, SQL-Update, Composer-Update oder Wechsel der E-/S-IDs nötig. Die Paketzeitstempel sind korrigiert; `touch` bleibt als gezielte Absicherung in der Anleitung.

Für den Praxistest zunächst eine bestehende Testentität mit Wert und Anmerkung nehmen: Feld im Schema von der bisherigen Kennung auf etwa `N1` ändern, Label erhalten, speichern, Jobs abarbeiten lassen. Wert, Anmerkung, Quellenstand und `{{#sws_value: E-ID | N1 }}` vergleichen. Danach erneut verarbeiten und prüfen, dass kein zweiter Schlüsselwechsel entsteht. Ein weiteres Testschema mit ebenfalls `N1` muss getrennte Standardkennungen erhalten.

## Grenzen

D8 schreibt keine Abfragen in Artikeln, Vorlagen oder externen Ontologiezuordnungen um. Solche Verweise auf den bisherigen Schlüssel müssen angepasst werden. Bei direkten JSON-Schemaänderungen ist eine ausdrückliche Zuordnung in der Änderungshistorie erforderlich; aus Labels wird keine Identität geraten. Bereits vor D8 ohne Zuordnung vorgenommene Schlüsselwechsel lassen sich nicht eindeutig aus dem jetzigen Stand rekonstruieren. Falls solche Wechsel vorhanden sind, müssen ihre konkreten alten und neuen Schlüssel zuerst geklärt werden.

Die vollständige Anleitung und Formatregeln stehen unter `docs/operations/test-candidate-de.md` und `docs/authoring/field-identifiers.md` im Paket.
