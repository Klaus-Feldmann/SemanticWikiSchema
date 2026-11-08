# SemanticWikiSchema 0.1.19

Schemaorientierte Datenerfassung mit Wikidata-/Wikibase-Quellen, automatischen SMW-Annotationen, Graphdarstellung und MCP-Zugriff. Die bestehende Eingabemaske und konfigurierbaren Eigenschaftszuordnungen bleiben erhalten.

Die SMW-Übernahme benötigt keinen Vorlagenaufruf mehr. Die Wiki-Module bleiben für Einzelabfragen erhalten; `Modul:NeoWiki/config` bleibt die bearbeitbare Konfiguration. Schema-Anfangswerte werden bei neuen Subjekten übernommen. URL-Voreinstellungen dürfen im Schema ohne Adresse gespeichert werden.

Installation ausschließlich über Composer: `aufeinwort/semantic-wikischema:dev-semantic-0.1.19`. Unterstützt ist die dokumentierte MediaWiki-1.47-/PHP-8.3-/SMW-7.4-Umgebung mit Legacy-Parser. Kein Docker-Angebot. Der vorhandene MCP-Patch bleibt unverändert enthalten.

Der Maintainer hat den Laufzeitstand auf dem Produktionsserver bestätigt; ein Installationstest dieses vollständigen Composer-Pakets steht noch aus. Dies ist keine 1.0-Freigabe. Originalautoren und Lizenzen bleiben erhalten; Jeroen De Dauw und Professional Wiki unterstützen diesen Fork nicht eigens.

[Installation](INSTALLATION.md) · [Prüfungen und Grenzen](VERIFICATION.md)
