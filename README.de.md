# Semantic WikiSchema (SWS) 0.7.0.2

SWS erfasst strukturierte Metadaten und Forschungsdaten getrennt vom Artikeltext. Ein Schema legt die Felder fest; Autoren bearbeiten die zugehörigen Entitäten unter dem Reiter **Daten**. Wiederverwendbare Definitionen und stabile Kennungen ermöglichen die gemeinsame Pflege und direkte Abfrage der Daten.

SWS bietet mehrsprachige Benennungen, Quellenbindungen zu Wikidata und lokaler Wikibase, Anmerkungen, Kategorien sowie Namensraumauswahl in Suchfeldern. Eigene Angaben lassen sich neben Quellenwerten verwalten. SMW-Annotationen, RDF-/JSON-Ausgaben und Graphdarstellung ergänzen die lokale Datenverwaltung bei entsprechender Einrichtung.

**[Funktionen, Einstellungen und Voraussetzungen](docs/README.md)** · **[Installation](docs/operations/install/de/README.md)** · **[English](README.md)**

Für die optionale KI-Anbindung steht [User AI (UAI)](docs/operations/install/de/erweiterungen.md#user-ai-uai) zur Verfügung. Entitäten lassen sich mit einem berechtigten MediaWiki-Konto direkt im Wiki bearbeiten.

## Wartung nach Schemaänderungen

Wird ein Feld nachträglich zum Pflichtfeld, prüft SWS die bereits gespeicherten
Entitäten bei der nächsten erfolgreichen Verarbeitung ihrer Wikiseiten. Ein
Neuladen im Browser übernimmt die zuletzt berechnete Wartungskategorie. Auch ein
erneutes Speichern ohne Inhaltsänderung ist kein verlässlicher Weg, diese
Verarbeitung auszulösen.

SWS stellt bei veröffentlichten Schemaänderungen Aktualisierungsjobs für die
Seiten mit Entitäten aus diesem Schema ein. Sind die Jobs erfolgreich
abgearbeitet und die Wartungskategorien aktuell, erübrigt sich ein manueller
Neuaufbau. Stehen die Kategorien danach noch auf dem alten Stand, etwa weil
die Jobs vor Einführung der Wartungskategorie liefen, führe im
MediaWiki-Hauptordner zunächst einen gezielten Neuaufbau aus. Ersetze
`12345` durch die numerische Seiten-ID der betroffenen Wikiseite.

```sh
php maintenance/run.php SemanticWikiSchema:RebuildData --page 12345
```

Ohne `--page` verarbeitet der Befehl den gesamten von SWS erfassten Bestand.
Eine Änderung an einem Schema mit vielen Vorlagen betrifft zunächst die
Vorlagenseiten mit den zugehörigen Entitäten. MediaWiki und weitere
Erweiterungen können für Seiten, die diese Vorlagen direkt oder indirekt
einbinden, Folgejobs einplanen. Bei weit verbreiteten Vorlagen kann das
einen großen Teil des Wikis betreffen.

Geprüfte Umgebung: MediaWiki 1.47.0-wmf.24, PHP 8.3.36, Legacy-Parser und Vector 2022. Zusätzliche Dienste und Erweiterungen sind je nach gewünschter Funktion erforderlich.

Softwaredesign: Klaus Feldmann / AufEinWort. Programmierung: ChatGPT/Sophos. [Mitwirkende](composer.json) · [Lizenz: GPL-2.0-or-later](COPYING).

[SWS 0.7.0.2 herunterladen](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/v0.7.0.2) · [Release notes](RELEASE-NOTES.md)
