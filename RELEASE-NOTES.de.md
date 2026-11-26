# Semantic WikiSchema 0.7.0.2

Änderungen gegenüber der veröffentlichten Version 0.6.0.

- **KI-Anbindung.** User AI (UAI) ist die optionale Anbindung für Assistenten. Der bisherige Patch für den eigenständigen MediaWiki MCP Server entfällt. Kategorienbasierte Recherchen können die tatsächlichen Seitenkategorien einschließlich der Kategorien aus eingebundenen Vorlagen mit den Entitätsdaten kombinieren.
- **Entitätsbearbeitung.** Direkte authentifizierte SWS-REST-Zugriffe verwenden eine MediaWiki-Browsersitzung. Berechtigte UAI-Aufrufe nutzen die dafür vorgesehenen Schnittstellen. Beim Ersetzen einer Entität kann die gelesene Basisrevision mitgegeben werden. Eine veraltete Basis wird mit HTTP 409 abgewiesen.
- **Suche.** Die Titelsuche findet Schemata anhand ihrer Benennungen. Die Entitätssuche berücksichtigt Benennungen und Beschreibungen.
- **Wikitext.** Der freie mehrzeilige Wikitext einer Entität wird auf der zugehörigen Wikiseite ausgeführt. Beim Einbinden einer Vorlage wird dieser Entitätswikitext nicht mit eingebunden.
- **Feldvalidierung.** Seiten mit ungültigen Feldwerten oder fehlenden Pflichtwerten erhalten mit der SWS-Verarbeitung die konfigurierbare Kategorie „SWS Wartung Feldeingabe“.
- **Kategoriefelder.** Feldbenennungen bleiben beim Einlesen eines Standardwerts erhalten.
- **Spezial:Version.** SWS erscheint unter „Semantische Erweiterungen“, SchemaBaseBridge unter „Wikibase-Erweiterungen“.

[Installation](docs/operations/install/de/README.md) · [Direktes Upgrade von 0.6.0](docs/operations/upgrade-0.7.0.2.md) · [User AI](https://www.mediawiki.org/wiki/Extension:User_AI)
