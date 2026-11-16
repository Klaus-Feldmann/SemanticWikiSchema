# Semantic WikiSchema 0.5.02

- SMW-Attributnamen folgen der Feldbenennung in der Wiki-Inhaltssprache, ersatzweise der Feldkennung, ohne automatischen Schema-ID-Präfix. Anmerkungen folgen derselben Benennung; ausdrückliche Zuordnungen haben Vorrang.
- SHORTDESC aus Seite/Schema hat Vorrang vor lokaler Wikibase und Wikidata. Die erste nicht leere Beschreibung wird Suchuntertitel und automatische SMW-Annotation Kurzbeschreibung. Eigene Schemafelder können zusätzliche Werte erzeugen.
- Die Suche liest den gespeicherten Untertitel und ruft beim Tippen keine externen Beschreibungen ab. Hauptsuche und Schema-Seitenfelder nutzen denselben Suchweg.
- Vollständige englische und deutsche Funktions-/Konfigurationsreferenz mit allen 22 registrierten Einstellungen, Array-Unteroptionen, Voraussetzungen und KI-unterstützter Installation und Schemaerstellung.
- Vorhandene Verarbeitungsdiagnose und die separat versionierte MCP-Integration bleiben erhalten.

[Installation](docs/operations/install/de/README.md) · [Referenz](docs/reference/SWS-0.5.02.de.md). Der Installer ist für Erstinstallation vorgesehen. Nach Austausch einer bestehenden Installation betroffene SWS-Seiten neu verarbeiten und Jobs betreiben. Für diese Ausgabeänderungen sind keine neue SQL-Struktur, Identitätsmigration oder Suchindex-Neuerstellung nötig.

Geprüfter Core: MediaWiki 1.47.0-wmf.24, PHP 8.3.36, Legacy-Parser, Vector 2022.
