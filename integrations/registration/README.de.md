# Optionale Wikidata-Registrierung

Der Helfer registriert die Wikidata-Q-ID als Seiteneigenschaft `unlinkedwikibase_id`. SchemaBaseBridge nutzt diese Zuordnung für Wikidata-Quellenfelder. Voraussetzung ist UnlinkedWikibase. Eine bereits eingerichtete Seiten-/Quellenzuordnung kann ohne diesen Helfer verwendet werden. Wikidata und lokale Wikibase werden nicht verändert.

[Einrichtung in LocalSettings.php](../../docs/operations/install/de/erweiterungen.md#optionale-wikidata-registrierung) · [English](README.md)

## Aufruf

```wikitext
{{#aewiwikidata:Q-ID|Wikipedia-Titel|automatisch (1/0)|Kategorie bei fehlendem Sitelink}}
```

Eine übergebene Q-ID wird geprüft. Ohne Q-ID und bei aktivierter Automatik erfolgt die Auflösung über den Sitelink der deutschsprachigen Wikipedia; ohne Titelangabe wird der aktuelle Seitentitel mit Namensraum verwendet. Ein ausdrücklich angegebener Wikipedia-Titel gilt für die Namensraumprüfung als ausdrücklicher Aufruf. Die Automatik ist keine allgemeine Identitätserkennung. Eine widersprüchliche zweite Q-ID im selben Parserlauf wird zurückgewiesen. UnlinkedWikibase-Jobs betreiben und die erlangte Zuordnung prüfen.

Die mitgelieferte [Minimalvorlage](Vorlage-SemanticWikiSchema-Wikidata.wikitext) kann als `Vorlage:SemanticWikiSchema/Wikidata` importiert werden. Auf der betreffenden Seite beispielsweise `{{SemanticWikiSchema/Wikidata|Q=Q2063917}}` aufrufen; alternativ die Parserfunktion direkt verwenden. Keine feste Personen-Q-ID in ein gemeinsames Personenschema für alle Seiten eintragen.

## Einstellungen des Helfers

Überschreibungen vor dem require_once setzen. Dies sind optionale Helfer-Variablen, keine registrierten SWS-Einstellungen.

| Variable | Standard | Bedeutung |
|---|---|---|
| `$wgAewiWikidataExplicitNamespaces` | `null` | Ausdrückliche Q-ID-/Titelaufrufe in nichtvirtuellen Namensräumen außer Weiterleitungen; Array begrenzt die Namensräume. |
| `$wgAewiWikidataAutoNamespaces` | `[ NS_MAIN ]` | Namensräume für die automatische Auflösung des aktuellen Titels; `null` hebt diese Beschränkung auf. |
| `$wgAewiWikidataSmwProperty` | `'Wikidata-ID'` | Zusätzliches SMW-Attribut für die Q-ID; `''` deaktiviert es. Ohne SMW auf `''` setzen. Bewusst eingerichteten Attributnamen und Datentyp beibehalten. |

Die optionale ID-Annotation ist von den Quellenfeldern und der ranggemäßen Kurzbeschreibung in SWS getrennt. Die zugehörige Magic-Word-Datei wird automatisch eingebunden. Nur eine Kopie des Helfers laden: require_once verhindert keine erneute Funktionsdefinition, wenn zwei verschiedene Pfade Kopien derselben Funktionen enthalten.
