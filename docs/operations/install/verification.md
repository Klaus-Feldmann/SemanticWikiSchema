# Database, jobs and first checks

For the operator completing activation after the [LocalSettings](configuration.md) changes.

From the MediaWiki root, using the PHP executable for the wiki:

```sh
php maintenance/run.php update --quick
php maintenance/run.php rebuildLocalisationCache --force
```

Use the installation's usual `--conf /path/to/LocalSettings.php` if it does not use the default configuration. If a command fails, stop and diagnose it before continuing. On a first installation, no identity migration command is needed.

Keep the site's normal job runner active. For a manual foreground runner:

```sh
php maintenance/run.php runJobs --wait
```

For production operation, supervise the job runner using the site's service manager or scheduled job setup. Restart it after maintenance; source fetching and schema-triggered processing depend on jobs being processed.

Functional checks:

1. Special:Version lists SemanticWikiSchema 0.4.03 and SchemaBaseBridge 0.21.1.
2. Special:Schemas (German: Spezial:Schemata) loads.
3. Create a schema with a text field. Confirm that its page uses an S-ID and its label can be edited.
4. On a test article, open Data, create an entity, save a value and reload. The entity has an E-ID and the saved value remains visible.
5. Change the schema label and reload the entity's Data view. It should use the new label without creating a second entity.
6. If source/SMW integration is configured, verify one source field and one resulting annotation after jobs finish.

The optional short entity URL and Graph Explorer require the separate [web-server / SPARQL setup](graph-explorer.md).
