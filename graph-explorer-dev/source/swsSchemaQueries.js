/** Stable schema identities, legacy schema names and revisioned labels share the class picker. */
export function createSchemaQueries(baseIri, language = 'de') {
 const base = new URL(baseIri);
 if (!['http:', 'https:'].includes(base.protocol) || base.username || base.password) throw new Error('Invalid RDF base');
 const preferred = /^[a-z]{1,8}(?:-[a-z0-9]{1,8})*$/i.test(language) ? language.toLowerCase() : 'de';
 const identified = JSON.stringify(new URL('schema-id/', base).href);
 const legacy = JSON.stringify(new URL('schema/', base).href);
 const entities = JSON.stringify(new URL('entity/', base).href);
 const label = `COALESCE(
   SUBSTR(MIN(CONCAT(
     IF(LANGMATCHES(LANG(?storedLabel), ${JSON.stringify(preferred)}), "0", IF(LANGMATCHES(LANG(?storedLabel), "en"), "1", "2")),
     STR(?storedLabel)
   )), 2),
   REPLACE(IF(STRSTARTS(STR(?class), ${identified}), STRAFTER(STR(?class), ${identified}), STRAFTER(STR(?class), ${legacy})), "_", " ")
 )`;
 const select = `SELECT ?class (${label} AS ?label) (COUNT(DISTINCT ?inst) AS ?instcount)`;
 const labels = `OPTIONAL { ?class <http://www.w3.org/2000/01/rdf-schema#label> ?storedLabel . }`;
 return {
  classTreeQuery: `${select} WHERE {
   ?inst a ?class .
   FILTER(STRSTARTS(STR(?inst), ${entities}))
   FILTER(STRSTARTS(STR(?class), ${identified}) || STRSTARTS(STR(?class), ${legacy}))
   ${labels}
  } GROUP BY ?class ORDER BY ?label ?class`,
  classInfoQuery: `${select} WHERE {
   VALUES (?class) { \${ids} }
   OPTIONAL { ?inst a ?class . FILTER(STRSTARTS(STR(?inst), ${entities})) }
   ${labels}
  } GROUP BY ?class ORDER BY ?class`
 };
}
