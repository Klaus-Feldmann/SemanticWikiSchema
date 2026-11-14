---
title: Parser Functions
order: 1
---
# Parser Functions

SWS provides these parser functions for use in wikitext.

| If you want to... | Use |
|-------------------|-----|
| Render a Subject visually on the page | [`{{#view}}`](#view) |
| Show the page's Main Subject in full, where you choose | [`{{#main_subject}}`](#main_subject) |
| Insert one property's value inline as text | [`{{#sws_value}}`](#sws_value) |
| Add a button that creates a Subject | [`{{#create_subject}}`](#create_subject) |
| Run a custom Cypher query and see the raw results | [`{{#cypher_raw}}`](#cypher_raw) |
| Run a custom SPARQL query and see the raw results | [`{{#sparql_raw}}`](#sparql_raw) |

For programmatic access from Lua modules, see the [Lua API](lua-api.md).

For definitions of terms like Subject, Schema, and Layout, see the [Glossary](../glossary.md).

## Permissions

Every parser function reads as the user the page is parsed for. Subjects that user cannot read are
treated as absent. `{{#cypher_raw}}` and `{{#sparql_raw}}` need the `sws-query` right.
`{{#view}}` and `{{#main_subject}}` only place a marker at parse time; the Subject they show is fetched
per viewer over the REST API, under that viewer's permissions. Output of the other functions is
cached separately for readers with different groups or rights, so it tracks page access only where
page access follows those; where a hook grants it per account, per IP or per session, see
[Restricted content](../operations/restricted-content.md).

## `{{#view}}`

Renders a Subject as HTML on the page using a [View Type](../glossary.md#view-type) (currently
`infobox`). Optionally uses a [Layout](../glossary.md#layout) to control which properties are shown
and how.

### Syntax

```
{{#view: }}                                           renders the current page's Main Subject
{{#view: <subjectId>}}                                renders the specified Subject
{{#view: subject=<subjectId>}}                        same, with the Subject specified as a named argument
{{#view: layout=<layoutName>}}                        renders the current page's Main Subject with the named Layout
{{#view: <subjectId> | layout=<layoutName>}}          renders the specified Subject with the named Layout
{{#view: subject=<subjectId> | layout=<layoutName>}}  same, with the Subject specified as a named argument
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `<subjectId>` (positional) | Subject ID to render: 15 characters starting with `s` for a Subject of this wiki, or `sourceKey:localId` for one from another [Source](../api/subject-format.md#ids). Defaults to the current page's Main Subject. |
| `subject=<subjectId>` | Named alternative to the positional form. Cannot be combined with the positional form. |
| `layout=<layoutName>` | Layout to apply. Without one, all properties are shown in schema order, except relation properties that point at [Dependent Subjects](../glossary.md#dependent-subject). |

### Notes

- Renders nothing when the Subject does not exist, or when no Subject is given and the page has no
  Main Subject.
- Rendering happens client-side, so the Subject view appears once the page's JavaScript has loaded.

### Examples

```
{{#view: }}
{{#view: s1abc5def6ghi78}}
{{#view: layout=CompanyOverview}}
{{#view: s1abc5def6ghi78 | layout=CompanyOverview}}
{{#view: subject=s1abc5def6ghi78 | layout=CompanyOverview}}
```

Unknown named arguments, more than one positional argument, or specifying the
Subject both positionally and as `subject=` produce a visible parser error.

## `{{#main_subject}}`

Shows the page's Main Subject where the wikitext places it, in full as Special:Subject does: with the Subjects
referencing it and those it references.

### Syntax

```
{{#main_subject: }}
```

### Notes

- Renders nothing when the page has no Main Subject.
- Replaces the page's automatic display of its Main Subject. For an infobox as well, add [`{{#view: }}`](#view).
- Works once per page: a second `{{#main_subject}}` shows nothing.
- On an old revision or a diff, shows the Main Subject as an infobox.
- Rendering happens client-side, so the Subject appears once the page's JavaScript has loaded.

Any argument produces a visible parser error.

## `{{#sws_value}}`

Returns the value of a single property from a Subject, formatted as a string.

### Syntax

```
{{#sws_value: <propertyName> }}
{{#sws_value: <propertyName> | page=<pageName> }}
{{#sws_value: <entityId> | <propertyName> }}
{{#sws_value: <propertyName> | separator=<separator> }}
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `propertyName` (positional) | The name of the property to read. Required. |
| `page` | Read from the Main Subject of the named page. Defaults to the current page. Cannot be combined with an explicit entity ID. |
| `entityId` (first positional argument when followed by a field) | Read the local entity with this E-ID, e.g. `E3`. Cannot be combined with `page`. |
| `separator` | Separator for multi-valued properties. Defaults to `, `. |

Field names such as `F12` are local to the entity’s schema; ordinary names work as well. `subject=` and `entity=` are not accepted. Unknown, duplicate or conflicting options and extra positional arguments return empty output.

### Output by property type

| Type | Output |
|------|--------|
| `text`, `url`, `date`, `dateTime` | The string value. Multiple values joined with `separator`. |
| `select` | The option's label. Multiple options joined with `separator`. Falls back to the stored value when the option cannot be looked up. |
| `number` | The number, e.g. `42` or `19.99`. |
| `boolean` | `true` or `false`. |
| `relation` | The target Subject's display name. Multiple targets joined with `separator`. Falls back to the target Subject ID when the target cannot be looked up or its page is not readable. |
| `monolingualText` | Each part's text, without its language. Multiple parts joined with `separator`. |

Boolean and number values are always rendered, even for `false` and `0` — these are not treated
as "empty".

### Text, links and template arguments

The result is text escaped with MediaWiki's Wikitext encoder. Field content does not execute templates, HTML, categories or formatting. Callers can use the result as a link target, link caption or template argument:

```wikitext
[[{{#sws_value: E3 | F4 }}]]
[[{{#sws_value: E3 | F4 }}|Geburtsjahr]]
{{My template | date={{#sws_value: E3 | F4 }} }}
```

`F4` must exist in the entity's schema; ordinary field names work as well. The caller chooses the link syntax. MediaWiki's normal title rules apply, including its restrictions on characters in titles. Use `[[:…]]` to link to a category or file page instead of categorizing or embedding it. If a value is empty, conditional template logic can suppress the surrounding link or label.

HTML character references may be present when another parser function receives the text. A consumer that requires raw data should use the structured SWS/Lua interfaces instead of decoding and executing returned text.

### Returns empty when

- The Subject does not exist on the page (or named page), or the Subject ID was not found.
- The Subject's page is not readable (see [Permissions](#permissions)).
- The Subject has no value for that property.
- The value is an empty collection (e.g. a multi-valued text property with no entries).

### Examples

```
Founded: {{#sws_value: Founded at}}
Status: {{#sws_value: Status | page=ACME Inc}}
Process owner: {{#sws_value: E3 | Process owner }}
Tags: {{#sws_value: Tags | separator=;}}
```

Passing a value to another extension's parser function:

```
{{#read-confirmation: audience={{#sws_value: Target audience}}}}
```

## `{{#create_subject}}`

Renders a button that opens the Subject creator without leaving the page.

### Syntax

```
{{#create_subject: }}
{{#create_subject: schema=<schemaName> }}
{{#create_subject: page=new }}
{{#create_subject: page=this }}
{{#create_subject: page=<pageName> }}
{{#create_subject: text=<buttonLabel> }}
```

### Parameters

| Parameter | Description |
|-----------|-------------|
| `schema=<schemaName>` | [Schema](../glossary.md#schema) to create the Subject with. Without it, the creator asks which Schema to use. |
| `page=new` | Stores the Subject on a new page: titled by its id on a subject-first wiki, and on a [page-first](../glossary.md#page-first-and-subject-first-wikis) wiki by the title the creator asks for, filled in from its label. |
| `page=this` | Stores the Subject on the page holding the button. Outside the content namespaces, `new` applies instead. |
| `page=<pageName>` | Stores the Subject on that page, creating it when it does not exist — which only the main namespace of a page-first wiki allows. |
| `text=<buttonLabel>` | The button's label. Defaults to `Create <schemaName>`, or `Create subject` without a Schema. |

### Notes

- With `page`, the user cannot change where the Subject goes. Without it, the creator asks on a page-first wiki, starting
  from a new page.
- The Subject becomes the page's Main Subject where that page has none, and joins the existing Subjects otherwise.
- A user who may not create the Subject is told why on clicking the button.
- The button appears once the page's JavaScript has loaded.

### Examples

```
{{#create_subject: }}
{{#create_subject: schema=Person}}
{{#create_subject: schema=Person | text=Add a person}}
{{#create_subject: schema=Exhibition | page=this}}
{{#create_subject: schema=Person | page=People directory}}
```

Positional arguments, unknown named arguments, a Schema that does not exist or whose Subjects are
[dependent](../glossary.md#dependent-subject), and an unusable page title produce a visible parser error. An empty value
counts as absent.

## `{{#cypher_raw}}`

Executes a read-only Cypher query and returns the raw results as JSON in a code block, for
development and debugging. Runs only when a Neo4j graph backend is configured; on a wiki
without one, `{{#cypher_raw: …}}` shows an error saying so. The [Graph Model](../api/graph-model.md)
describes the node and relationship structure to query.

For formatted result rendering, build it in a template with Lua
[`nw.query()`](lua-api.md#nwquerycypher-params).

### Syntax

```
{{#cypher_raw: <cypherQuery>}}
```

### Notes

- Only read queries are allowed. Anything that creates, modifies, or deletes data is rejected,
  including `CALL` (even for read-only procedures).
- Requires the `sws-query` right (see [Permissions](#permissions)).
- Results are capped at the `maxRows` and stopped at the `timeoutSeconds` of the `default` tier of
  `$wgSWSQueryLimits` (see [Limits and tiers](../api/query-api.md#limits-and-tiers)); rows beyond
  the cap are dropped silently.
- Errors (rejected queries, syntax errors, the database being unavailable, etc.) render as a
  styled error message in place of the result.
- Output is HTML-escaped, so query results containing `<`, `>`, `&`, etc. display safely.
- The output is wrapped in `<div class="mw-sws-cypher-result"><pre>` and the error message in
  `<div class="error">`, so you can target either with CSS.

### Examples

```
{{#cypher_raw: MATCH (s:Subject) RETURN s.name LIMIT 10}}

{{#cypher_raw: MATCH (s:Subject) WHERE 'Company' IN labels(s) RETURN s.name, s.`Founded at`}}
```

## `{{#sparql_raw}}`

Executes a read-only SPARQL query against the first configured [SPARQL store](../operations/installation.md#optional-sparql-graph-stores)
and returns the raw results as JSON in a code block. The SPARQL counterpart of `{{#cypher_raw}}`, for
development and debugging. Runs only when a SPARQL store is configured; on a wiki without one,
`{{#sparql_raw: …}}` shows an error saying so.

The JSON is the W3C [`application/sparql-results+json`](https://www.w3.org/TR/sparql11-results-json/)
document — the standard `head` / `results` structure (or `boolean` for an `ASK` query).

### Syntax

```
{{#sparql_raw: <sparqlQuery>}}
```

### Notes

- Read-only: the query cannot modify the store.
- Requires the `sws-query` right (see [Permissions](#permissions)).
- No row cap is applied — the full results document is returned. Queries stop at the `timeoutSeconds`
  of the `default` tier of `$wgSWSQueryLimits` (see
  [Limits and tiers](../api/query-api.md#limits-and-tiers)).
- Errors (a query the store rejects, the store being unavailable, etc.) render as a styled error message
  in place of the result.
- Output is HTML-escaped, so results containing `<`, `>`, `&`, etc. display safely.
- The output is wrapped in `<div class="mw-sws-sparql-result"><pre>` and the error message in
  `<div class="error">`, so you can target either with CSS.
- Pages are projected into named graphs, so which of them an unscoped query reaches depends on the store — see
  [RDF Export](../api/rdf-export.md#iri-scheme).

### Examples

```
{{#sparql_raw: SELECT ?label WHERE { ?s <http://www.w3.org/2000/01/rdf-schema#label> ?label } LIMIT 10}}
```
