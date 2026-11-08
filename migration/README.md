# Existing-data identifier migration

Run MigrateIdentifiers.php from the existing MediaWiki root through maintenance/run.php using the absolute path of this file's sibling. The default invocation is a read-only report. It writes private PLAN.json and BESTAND.json outside the web tree. See publication/MIGRATION-0.1.22.md before any --apply operation.

The engine preserves numeric slot/model IDs and stored content references. It renames extension tables, columns and indexes and updates the exact model/slot/managed-marker metadata. No global article-text replacement or content-blob rewrite is performed. Existing target identifiers and unexpected historical slot models are refused.

Production adapter: checked MySQL/MariaDB interfaces with version guards. Engine behavior was tested against real SQLite fixture databases; SQL-adapter behavior was checked with recorded MySQL statements. A live MySQL/MariaDB migration remains pending. FinalizeIdentifiers.php runs only after code/configuration/module switching and queues normal processing for affected subject pages.
