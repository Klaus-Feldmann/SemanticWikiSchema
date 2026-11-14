# Optionale Anbindungen

Für den Betreiber, nachdem die lokale Dateneingabe funktioniert.

## Graph Explorer

Die Browserdateien sind enthalten. Für den Graphen wird ein eigener SPARQL-Dienst benötigt; bestätigt ist Fuseki 5.3.0. Die [Graph-Anleitung mit Befehlen](../graph-explorer.md) beschreibt Dienst, LocalSettings und Apache getrennt. Der Beispieldienst heißt `/sws` und läuft auf Port 3331. Eine vorhandene SMW-SPARQL-Datenbank bleibt davon getrennt.

Die [Apache-Beispieldatei](../../../../publication/apache-sws.example.conf) muss in den vorhandenen VirtualHost übernommen und an Pfade sowie erlaubte Zugriffe angepasst werden. Sie ist kein vollständiger VirtualHost. Der direkte SPARQL-Proxy übernimmt nicht automatisch die Seitenrechte von MediaWiki.

`graph-explorer/config.js` muss zum API-Pfad und zur RDF-Basis des Wikis passen. Nach Einrichtung und Befüllung des Stores die Klassen und Instanzen auswählen und auf die Zeichenfläche übernehmen. Ein neuer Aufruf zeichnet nicht automatisch alle Entitäten.

## Wikidata und lokales Wikibase

Ein Quellenfeld braucht eine zugeordnete Q-ID und eine passende P-ID. SchemaBaseBridge ist enthalten; eigene Eingaben und Labels haben Vorrang. Der mitgelieferte optionale Registrierungshelfer ist unter `integrations/registration/` beschrieben. Seine automatische Zuordnung nutzt die deutschsprachige Wikipedia; er ist keine Pflicht für lokale Datenpflege.

## SMW, Lua und MCP

SMW separat installieren und konfigurieren. Ohne ausdrückliche Zuordnung dient beispielsweise `S1:F12` als schemaeigener Attributname. Mehrsprachige Feldbenennungen ändern diesen Namen nicht. Bestehende Attribute müssen geeignete Datentypen besitzen; zusätzliche Namensräume sind in SMW gesondert freizugeben. SWS kann auch ohne SMW Daten speichern und direkt abfragen.

Scribunto stellt die Lua-Abfragen bereit. Der [MCP-Patch für SWS 0.5.0 und MediaWiki MCP 0.21.0](https://github.com/Klaus-Feldmann/SemanticWikiSchema/releases/tag/mcp-sws-0.5.0) ist ein separates Integrationspaket; der SWS-Installer richtet keine MCP-Anmeldung ein.

[Englische Detailanleitung](../integrations.md) · [Installationsübersicht](README.md)
