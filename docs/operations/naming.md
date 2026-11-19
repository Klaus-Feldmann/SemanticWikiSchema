# Benennungen vor der Datenerfassung festlegen

Für Administratoren, die vor der ersten Datenerfassung die nach außen verwendeten Systembegriffe festlegen. Die fachlichen Feldbezeichnungen bleiben im Schema.

## Gespeicherte SMW-Eigenschaftsnamen

Die folgenden MediaWiki-Nachrichten bestimmen die Vorgabenamen in der Wiki-Inhaltssprache. Lege die gewünschten Texte vor der Datenerfassung als entsprechende MediaWiki-Seiten an. Die persönliche Oberflächensprache ändert diese Namen nicht.

| Nachricht | Deutsche Vorgabe |
|---|---|
| `sws-smw-property-caption` | Quellenbezeichnung |
| `sws-smw-property-caption-page` | Quellenbezeichnungsseite |
| `sws-smw-property-description` | Kurzbeschreibung |
| `sws-smw-property-entity` | WikiSchema-Entität |
| `sws-smw-property-field` | WikiSchema-Feld |
| `sws-smw-property-label` | Quellentitel |
| `sws-smw-property-url` | Quellen-URL |

Ausdrückliche Zuordnungen in `$wgWikiSchemaSmwMapping` haben Vorrang. Neben `properties` und `urlEntries` kann `descriptionProperty` den Namen für die automatisch ermittelte Seitenbeschreibung festlegen. Die Auswahl der Beschreibung und eigene Schemawerte bleiben davon unabhängig.

Verwende gültige SMW-Eigenschaftsnamen; insbesondere keine leeren Namen, Zeilenumbrüche, `=`, `|`, `::` oder Wikitext-Klammern. Lege die zugehörigen Attributseiten mit geeigneten SMW-Datentypen an. Bestehende Typdefinitionen werden nicht automatisch verschoben.

Beim Wechsel von 0.5.02 heißt die deutsche Entitätszuordnung standardmäßig **WikiSchema-Entität** statt **WikiSchema-Subjekt**. Die anderen deutschen Eigenschaftsvorgaben bleiben gleich. Prüfe Abfragen auf den alten Namen, bevor du vorhandene Annotationen neu aufbauen lässt. Eine ausdrücklich konfigurierte bisherige Zuordnung bleibt erhalten.

Nach späteren Namensänderungen müssen die betroffenen Seiten vollständig neu verarbeitet werden. Bei einer gebündelten Umstellung verwende nach einem erfolgreichen Einzeltest im MediaWiki-Hauptverzeichnis:

```sh
php maintenance/run.php SemanticWikiSchema:RebuildData
```

Eine Nachrichtenseitenänderung kann für bereits erfasste Abhängigkeiten Jobs auslösen. Nach der erstmaligen Installation und bei noch alten Abhängigkeiten ist der ausdrückliche Wiederaufbau der verlässliche Übergangsweg. Keine neue Identitätsmigration, kein SQL-Strukturwechsel und kein erzwungener Quellenabruf sind für diese Benennungen erforderlich. Folgejobs und abfragende Seiten können nachlaufen. Sichtungsregeln bleiben bestehen.

## Anzeigen, Kommentare und Downloads

Die übrigen neuen Nachrichten steuern Standard-Bearbeitungskommentare, Entitätsfehlermeldungen, Lua-Fehler und Downloadnamen. Downloadmuster enthalten genau einmal `$1` für die ID, beginnen mit Buchstabe oder Ziffer und verwenden sonst nur ASCII-Buchstaben, Ziffern, `_` und `-`. Die Dateiendung wird vom Exportformat bestimmt. Ungültige Muster fallen auf einen sicheren technischen Namen zurück. Bereits exportierte Dateien oder gespeicherte Versionskommentare werden nicht umbenannt.

`Spezial:SWSJson` ist der bevorzugte Alias der Entwicklungsansicht; `NeoJson` bleibt gültig. Das ausgelieferte Lua-Hilfsmodul akzeptiert bevorzugt `entitaet=` und weiterhin `subjekt=`. Vergleiche die mitgelieferte Modulfassung mit örtlichen Anpassungen, bevor du eine Wiki-Modulseite ersetzt. Technische REST-Pfade, JSON-Schlüssel, IDs und Namensräume bleiben erhalten.
