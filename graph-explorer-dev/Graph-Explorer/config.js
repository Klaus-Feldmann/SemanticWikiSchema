/* Deployment configuration. The RDF base must match SWS's RDF base. */
globalThis.SWSGraphConfig = Object.freeze({
  baseIri: location.origin + '/',
  apiUrl: '/w/api.php',
  sparqlUrl: '/graph-explorer/sparql?default-graph-uri=urn%3Ax-arq%3AUnionGraph'
});
