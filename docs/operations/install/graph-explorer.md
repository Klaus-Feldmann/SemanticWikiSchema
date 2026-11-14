# Graph Explorer and Fuseki

For the operator adding a graph backend after SWS works locally. Fuseki 5.3.0 is the live-confirmed SPARQL service. The Graph Explorer files are bundled; the RDF service is separate.

## 1. Create a separate SWS service

Use a persistent directory writable by the Fuseki service account. A standalone Fuseki launch has this form; adapt the executable and directory to the server:

```sh
fuseki-server --localhost --port=3331 --tdb2 --update --loc=/var/lib/fuseki/sws /sws
```

Run it under the site's service manager. An existing SMW SPARQLStore is a different store and must not be reused as an empty SWS dataset.

## 2. Connect SWS

Add to LocalSettings.php:

```php
$wgSWSSparqlStores = [
    [
        'name' => 'sws',
        'updateUrl' => 'http://127.0.0.1:3331/sws/update',
        'queryUrl' => 'http://127.0.0.1:3331/sws/query',
        'projection' => 'native',
    ],
];
```

For a new store, populate the current projections from the MediaWiki root:

```sh
php maintenance/run.php SemanticWikiSchema:RebuildData
```

This reads the current SWS data; it is not an import command. A store added after entities exist needs rebuilding before those entities appear in the graph.

## 3. Browser endpoint and links

For Apache with rewrite/proxy modules, the supplied [example](../../../publication/apache-sws.example.conf) routes `/graph-explorer/sparql` to `/sws/query` and resolves short entity/schema IRIs through SWS. Insert it into the existing VirtualHost and adapt the filesystem path and access rules. The example keeps the SPARQL URL local-only until the operator deliberately grants the intended browser clients access. A direct SPARQL proxy is not automatically protected by MediaWiki page permissions.

Use the existing web server's configuration test and reload commands. No global MediaWiki short-URL change is required.

The bundled `graph-explorer/config.js` defaults to:

```js
globalThis.SWSGraphConfig = Object.freeze({
  baseIri: location.origin + '/',
  apiUrl: '/w/api.php',
  sparqlUrl: '/graph-explorer/sparql?default-graph-uri=urn%3Ax-arq%3AUnionGraph'
});
```

Adapt `/w/api.php` if `$wgScriptPath` differs. `baseIri` must match `$wgSWSRdfBaseUri` (default: the wiki's canonical server plus its base slash). SWS 0.5.0's graph queries recognize `/schema-id/S…` and display stored schema labels.

Open `/w/extensions/SemanticWikiSchema/graph-explorer/envendpoint.html` on a `/w` wiki. Select classes and instances to add nodes to the canvas. A new canvas does not automatically contain all entities. Test an entity link such as `/entity/E1` and a schema link such as `/schema-id/S1`.
