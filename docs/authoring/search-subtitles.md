# Suchuntertitel

Auf durch SWS verarbeiteten Seiten wählt die Hauptentität die erste nicht leere Kurzbeschreibung: SHORTDESC aus Seite oder Schema, lokale Wikibase, Wikidata. SMW-Werte sind keine Eingabe dieser Auswahl.

Die Auswahl wird als Seitenmetadatum gespeichert und als SMW-Attribut Kurzbeschreibung ausgegeben, sofern SMW verfügbar ist. Ein ausdrücklich angelegtes Schemafeld erzeugt unabhängig davon seine Annotation. Ein gleichnamiges Feld kann einen weiteren SMW-Wert hinzufügen; der Suchuntertitel bleibt die ranggemäß ausgewählte Beschreibung.

Hauptsuchfeld und Schema-Seitenfelder verwenden den gespeicherten Suchuntertitel. Die Suche ruft beim Tippen keine Quellenwerte ab. Nach einer Änderung die Seitenverarbeitung und Jobs abschließen lassen. Vorhandene Seiten müssen nach dem Upgrade einmal neu verarbeitet werden. Seiten ohne SWS-Projektion behalten die bisherige MediaWiki-Beschreibung.
