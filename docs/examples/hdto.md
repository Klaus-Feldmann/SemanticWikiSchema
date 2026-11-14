# HDTO-Mapping für eigene Schemata erzeugen

Für Betreiber eines SPARQL-gestützten Kunst-/Kulturerbebestands. Das Beispiel bildet ein Werk, dessen Erzeuger und eine bewahrende Institution auf HDTO/CIDOC CRM ab. Es richtet keine Schemata oder Entitäten ein.

Voraussetzung sind passende eigene Schemata: Werk, Künstler und Institution. Die drei S-IDs ausdrücklich wählen; keine Beispielnummer auf ein vorhandenes Personenschema übertragen. Das Werk-Schema benötigt Beziehungen zum Erzeuger und zur bewahrenden Institution sowie ein Bildfeld. „Located at“ im Ausgangsbeispiel wird als bewahrende Institution interpretiert, nicht als beliebige geographische Ortsangabe.

Aus dem Erweiterungsverzeichnis, mit den eigenen IDs und Feldnamen:

```sh
python3 examples/hdto/build_mapping.py \
  --artwork S21 --artist S22 --museum S23 \
  --creator Erzeuger --keeper Institution --image Bild > /tmp/HDTO.json
```

Die S-IDs sind lediglich Beispiele. Auch schemaeigene Feldnamen wie `F1` sind möglich. Die Ausgabe enthält ID-basierte `schemaIds`; Labeländerungen der Schemata ändern die Zuordnung nicht. Vor einer manuellen Übernahme auf eine Mapping-Seite Definitionen und Feldnamen überprüfen. Keine vorhandene Mapping-Seite ungeprüft ersetzen. Der Generator prüft die Eingabeform, nicht den Bestand des Wikis.

Der verwendete HDTO-Präfix ist `http://isl.ics.forth.gr/ontology/echoes/`, entsprechend dem übernommenen Beispielstand. Die Ontologie-Adresse war upstream noch Gegenstand von Änderungen; die gewünschte Ontologieversion vor fachlichem Einsatz prüfen. Hier wurde keine aktuelle externe Ontologieprüfung durchgeführt.

Quelle: [NeoWiki #1861](https://github.com/ProfessionalWiki/NeoWiki/pull/1861), Commit `b6548939bc6742e6a00b34fa86c93e5f82584fe2`; GPL-2.0-or-later. Das namensbasierte Original liegt zur Herkunftsdokumentation unter `examples/hdto/HDTO.named-example.json`.
