---
title: Extending SWS
order: 1
---
# Extending SWS

Other MediaWiki extensions can add Property Types and View Types, contribute page metadata and revision choices,
supply Subjects from outside this wiki, keep a graph store of their own in sync, and use SWS's PHP services and
Vue components. The concepts used here — Subject, Schema, Property Type, Page Property — are defined in the
[Glossary](../glossary.md).

[RedHerb](https://github.com/ProfessionalWiki/NeoWiki/tree/master/tests/RedHerb) is a minimal example extension in the SWS repository; the fastest start is to copy
the file it uses for your extension point and adapt it.

SWS is pre-1.0. Every extension point may change without notice until 1.0.

## What you can build

Contribute to SWS:

- [Property Types](property-types.md) — a new kind of value: its validation, search text, projection, and editing
  and display components.
- [View Types](view-types.md) — a new visual format for rendering a Subject.
- [Page Property Providers](page-properties.md) — key/value metadata on the Page node in the graph, and refreshing
  it without an edit.
- [Revision policy](revision-policy.md) — which revision of a page SWS publishes, when your extension decides
  what readers see.
- [Edit notices](edit-notices.md) — a message shown before a user edits a Subject.
- [Subject Sources](subject-sources.md) — Subjects and Schemas from somewhere other than this wiki's revision
  slots; they are read-only.
- [Graph Database Backends](graph-database-backends.md) — a store of your own that SWS keeps in sync; it gets
  no query surface of its own yet.

Use SWS from your own code:

- [Using SWS from PHP](php.md) — Subjects and Cypher from hooks and special pages.
- [Using SWS from JavaScript](javascript.md) — the public JS API, displaying values, mounting Vue features,
  TypeScript.

## Getting started

Declare the dependency in your `extension.json`:

```json
"requires": {
	"extensions": {
		"SWS": "*"
	}
}
```

### Backend registration

Register backend extension points through the `SWSRegistration` hook:

```json
"Hooks": {
	"SWSRegistration": "ProfessionalWiki\\MyExt\\MyExtHooks::onSWSRegistration"
}
```

```php
public static function onSWSRegistration( SWSRegistrar $registrar ): void {
	$registrar->addPropertyType( new ColorType() );
	$registrar->addPagePropertyProvider( new StaticPagePropertyProvider() );
}
```

Example: [`src/RedHerbHooks.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbHooks.php). Registering a Property Type or View Type under a name
already in use — a built-in's or another extension's — replaces it: the later registration wins, in the backend
and frontend registries independently.

### Frontend module

Register frontend extension points from a ResourceLoader module that depends on `ext.sws`. The
`SWSGetFrontendModules` hook loads it wherever SWS's UI loads; on pages of your own, load it as any module.

```json
"ResourceModules": {
	"ext.myext": {
		"class": "MediaWiki\\ResourceLoader\\CodexModule",
		"dependencies": [ "vue", "ext.sws" ],
		"packageFiles": [ "init.js" ]
	}
}
```

```php
class MyExtFrontendModulesHook implements SWSGetFrontendModulesHook {

	public function onSWSGetFrontendModules( array &$modules, OutputPage $out, Skin $skin ): void {
		$modules[] = 'ext.myext';
	}

}
```

Example: [`src/RedHerbFrontendModulesHook.php`](https://github.com/ProfessionalWiki/NeoWiki/blob/master/tests/RedHerb/src/RedHerbFrontendModulesHook.php). The module is plain
JavaScript with no build step; [TypeScript](javascript.md#authoring-in-typescript) is optional.

## Internal surfaces

Not for extensions to build on, however reachable:

- **SWS's rendered HTML.** The `.ext-sws-view` placeholders and `data-mw-sws-*` attributes are the
  private contract between SWS's backend and frontend for mounting Views: do not select, scrape, restyle or
  replace them. To place a Subject rendering in page content use [`{{#view}}`](../authoring/parser-functions.md);
  for your own format register a [View Type](view-types.md); for fully custom UI fetch the data through the
  [REST API](../api/rest-api.md) or the [JS API](javascript.md) and render it yourself.
- **The internal Neo4j client.** `SWSExtension::getInstance()->getNeo4jClient()` and `getReadOnlyNeo4jClient()`
  return the Laudis client SWS itself uses ([ADR 13](../adr/013-restrict-neo4j-access.md)). Query through the
  [query service](php.md#running-cypher-queries), which rejects writes and enforces limits; anything written to the
  graph directly is overwritten by the next save or rebuild of the pages involved. Page-level metadata has a durable
  path in [Page Property Providers](page-properties.md); other nodes and relationships have none short of a
  [Graph Database Backend](graph-database-backends.md) of your own.
