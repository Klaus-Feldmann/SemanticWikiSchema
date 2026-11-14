# Schemaeigene Feldkennungen

Ein Feld wird durch Schema-ID und exakte Feldkennung bestimmt: S1/N1 und S2/N1 sind verschiedene Felder. `{{#sws_value: E3 | N1 }}` ermittelt das Schema über E3. `F1`, `N1`, `Z1` und beschreibende Kennungen werden gleich behandelt; die bestehenden Zeichenregeln gelten weiterhin. Es gibt keine automatische Nummernvergabe. Die Reihenfolge und mehrsprachige Benennungen sind unabhängig von der Kennung.

## Eindeutigkeit

Eine Feldkennung darf innerhalb eines Schemas nur einmal vorkommen. Editor und JSON-Speicherwege weisen doppelte Schlüssel zurück, bevor ein JSON-Dekodierer einen Eintrag verdecken kann. Andere Schemata dürfen denselben Schlüssel verwenden. Labels sind keine alternativen Abfrageschlüssel.

## Feldkennung ändern

Im Schemaeditor die vorhandene Feldkennung bearbeiten und das Schema speichern. Der Editor hält die Zuordnung vom bisherigen zum neuen Schlüssel fest; Zwischenstände während des Tippens werden nicht als eigene Änderungen gespeichert. Die bestehenden Seitenjobs übernehmen Werte, Relationen, Anmerkungen und alle Sprachen gespeicherter Quellenstände. Der Artikeltext wird nicht verändert.

Die Übernahme erfolgt je Entität genau einmal pro Änderungsschritt. Eine später verarbeitete Seite holt mehrere Schritte in der gespeicherten Reihenfolge nach. Eine gelöschte Kennung wird nicht global reserviert; eine spätere Wiederverwendung führt bereits verarbeitete Entitäten nicht erneut durch alte Schritte. Eine Kollision mit vorhandenen Zielwerten bricht die Übernahme ab, statt Daten zu überschreiben. Bei einem Jobfehler den Fehlerbericht prüfen und die Kollision berichtigen, bevor erneut verarbeitet wird.

Während eine Übernahme aussteht, werden gewöhnliche Entitätsänderungen zurückgewiesen. Den Jobrunner abarbeiten lassen und den Editor neu öffnen. Eine Umbenennung und eine Datentypänderung sind getrennte Schemaänderungen. Bei direkten JSON-Änderungen muss die Zuordnung ausdrücklich als neuer Eintrag in `fieldKeyChanges` vorliegen; die vorhandene Historie bleibt erhalten. Beispiel:

```json
"fieldKeyChanges": [ { "Klassenzuordnung": "N1" } ]
```

Die Entität speichert den abgearbeiteten Stand intern als `fieldKeyVersion`. Neue Entitäten beginnen beim aktuellen Stand des Schemas. Frühere, bereits ohne Zuordnung vorgenommene Umbenennungen werden nicht aus gleichen Labels oder ähnlichen Werten geraten. Abfragen, Vorlagen und externe Ontologiezuordnungen mit dem alten Schlüssel müssen geprüft und angepasst werden; Wikitext wird nicht automatisch umgeschrieben.

## RDF und Semantic MediaWiki

Alle Felder identifizierter Schemata verwenden Prädikate wie `/schema-id/S1/field/N1`; andere Schemata und Quellen haben eigene Pfade. Jedes Pfadsegment wird einzeln kodiert. Leerzeichen, Unterstriche und Unicode werden nicht zu derselben Kennung zusammengezogen. Graph und vollständige RDF-Ausgabe verwenden dieselbe Regel.

Ohne ausdrückliche Zuordnung heißt das SMW-Attribut beispielsweise `S1:N1`; Prozent- und Gleichheitszeichen im Schlüssel werden eindeutig kodiert, damit sie nicht als SMW-Parametertrenner wirken. Anmerkungen verwenden denselben Namen mit dem konfigurierten Suffix. Eine gemeinsame fachliche Eigenschaft kann ausdrücklich zugeordnet werden:

```php
$wgWikiSchemaSmwMapping['properties']['S1']['N1'] = 'Geburtsdatum';
```

Eine vorhandene ausdrückliche SMW-Zuordnung am bisherigen Schlüssel bleibt bei der Übernahme nutzbar. Eine Zuordnung am aktuellen Schlüssel hat Vorrang. Die Zuordnung gilt auch für Anmerkungen. Neue SMW-Attribute benötigen wie bisher den passenden Datentyp; eine automatische Anlage von Attributseiten wird nicht eingeführt. Nach dem Upgrade betroffene Seiten neu projizieren, damit alte globale Feldprädikate und bisherige Standardattribute ersetzt werden.

## Mehrsprachige Feldbenennungen

Unter „Feldbenennungen“ je Sprache ein Label ergänzen. Jede Sprache darf nur einmal vorkommen. Die Anzeige wählt Benutzersprache, MediaWiki-Rückfallsprachen, Wiki-Inhaltssprache und schließlich die Feldkennung. Im Graph beginnt die Auswahl mit dessen Datensprache. Schemaanzeige und Feldliste zeigen die Kennung zusätzlich, wenn das Label abweicht.

Ein Labelwechsel verändert keine Kennung, Werte, Quellenzuordnungen oder konfigurierten Linkziele. Ohne ausdrücklich gesetztes Linkziel verwendet der Feldlink weiterhin die Feldkennung. RDF ergänzt sprachmarkierte `rdfs:label`-Angaben am bestehenden Feldprädikat.
