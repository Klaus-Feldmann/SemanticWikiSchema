# Optionale Anbindungen

Für den Betreiber, nachdem die lokale Dateneingabe funktioniert.

## Graph Explorer

Die Browserdateien sind enthalten. Für den Graphen wird ein eigener SPARQL-Dienst benötigt; bestätigt ist Fuseki 5.3.0. Die [Graph-Anleitung mit Befehlen](../graph-explorer.md) beschreibt Dienst, LocalSettings und Apache getrennt. Der Beispieldienst heißt `/sws` und läuft auf Port 3331. Eine vorhandene SMW-SPARQL-Datenbank bleibt davon getrennt.

Die [Apache-Beispieldatei](../../../../publication/apache-sws.example.conf) muss in den vorhandenen VirtualHost übernommen und an Pfade sowie erlaubte Zugriffe angepasst werden. Sie ist kein vollständiger VirtualHost. Der direkte SPARQL-Proxy übernimmt nicht automatisch die Seitenrechte von MediaWiki.

`graph-explorer/config.js` muss zum API-Pfad und zur RDF-Basis des Wikis passen. Nach Einrichtung und Befüllung des Stores die Klassen und Instanzen auswählen und auf die Zeichenfläche übernehmen. Ein neuer Aufruf zeichnet nicht automatisch alle Entitäten.

## Wikidata und lokales Wikibase

Ein Quellenfeld braucht eine zugeordnete Q-ID und eine passende P-ID. SchemaBaseBridge ist enthalten; eigene Eingaben und Labels haben Vorrang.

## Optionale Wikidata-Registrierung

Für Wikidata-Feldwerte braucht SWS eine Zuordnung der Seite zu einer Quellenentität. Besteht diese bereits, ist kein weiterer Registrierungshelfer nötig. Die mitgelieferte Datei `AewiWikidataRegistrierung.php` kann eine Q-ID als Seiteneigenschaft `unlinkedwikibase_id` registrieren, die SchemaBaseBridge ausliest.

Für diesen Weg UnlinkedWikibase installieren und laden. In LocalSettings.php ergänzen:

```php
$wgAewiWikidataAutoNamespaces = [ NS_MAIN ];
// Ohne Semantic MediaWiki die optionale SMW-Ausgabe des Helfers abschalten:
// $wgAewiWikidataSmwProperty = '';
require_once "$IP/extensions/SemanticWikiSchema/integrations/registration/AewiWikidataRegistrierung.php";
```

Das Laden registriert `#aewiwikidata`; zusätzlich ist ein Aufruf auf der Seite oder über deren Vorlage/Schema erforderlich. Bei bekannter Zuordnung auf der betreffenden Seite beispielsweise:

```wikitext
{{#aewiwikidata:Q2063917}}
```

Ohne Q-ID kann der Helfer den Seitentitel über den Sitelink der deutschsprachigen Wikipedia auflösen. Dies ist keine sprachunabhängige oder garantierte Identitätszuordnung. Alle Optionen und die mitgelieferte Minimalvorlage stehen in der [Helferreferenz](../../../../integrations/registration/README.de.md). Jobs betreiben und die erlangte Zuordnung prüfen. Der Helfer schreibt nicht in Wikidata oder die lokale Wikibase.

Nur eine Dateikopie laden. Wird der Helfer bereits über einen anderen Pfad eingebunden, nicht zusätzlich die Paketkopie laden. Die zugehörige Magic-Word-Datei bindet er selbst ein. Seine Einstellungen sind von den 22 registrierten SWS-Optionen getrennt. Standortspezifische Speicherbenennungs- oder Deepcat-/Fuseki-Dateien sind keine allgemeinen SWS-Voraussetzungen.

## SMW, Lua und MCP

SMW separat installieren und konfigurieren. Der Attributname folgt der Feldbenennung in der Wiki-Inhaltssprache, ersatzweise der technischen Feldkennung, ohne automatischen Schema-ID-Präfix. Anmerkungen folgen derselben Benennung. Eine Änderung der maßgeblichen Benennung wirkt nach erneuter Verarbeitung auf den SMW-Namen; eine ausdrückliche Zuordnung hat Vorrang. Bestehende Attribute müssen geeignete Datentypen besitzen; zusätzliche Namensräume sind in SMW gesondert freizugeben. SWS kann auch ohne SMW Daten speichern und direkt abfragen.

Scribunto stellt die Lua-Abfragen bereit. Das [vollständige Integrationspaket für MediaWiki MCP 0.21.0](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0) ist ein separates Integrationspaket; der SWS-Installer richtet keine MCP-Anmeldung ein.

[Englische Detailanleitung](../integrations.md) · [Installationsübersicht](README.md)
