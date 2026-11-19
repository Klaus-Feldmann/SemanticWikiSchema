# SWS capabilities and terminology

SWS entities (historically called subjects in API/tool names) have persistent IDs, schema membership, typed field values and a wiki page. Discover schema names with sws-list-schemas, then fields, labels and select option IDs with sws-get-schema. Never assume a field key, schema ID, namespace number or option ID from another wiki. Field keys and their displayed names differ.

sws-search-subjects searches entity labels/IDs; it does not search purpose or description fields. sws-query-entities filters actual stored fields within a selected schema. sws-get-subject and sws-get-page-subjects inspect selected entities. swsbase-get-data-view is a read model with own/external/effective values; it is not a replacement-write payload.

Installed extensions and visible tools do not prove backend availability or token permissions. The context reports detected extensions, version evidence and backend configuration. Connectivity, token permissions and data freshness remain unverified until appropriate evidence exists. Neo4j is optional: local schema/entity reads do not require it. Cypher requires a configured graph and sws-query permission; SMW and SWSBase are separate capabilities. A schema catalogue can exist without entities for a requested namespace or purpose.

No SWS schema or property changes are required by this patch. Renaming fields or changing schemas can schedule extensive MediaWiki/SMW jobs. Such changes are separate work requiring user authorization.

sws-get-query-capabilities checks configured graph backends. sws-sparql-query wraps the existing SWS SPARQL endpoint if a store is configured. Cypher and SPARQL are not required for stored schema/entity reads or SMW queries.

Application/use-case descriptions are optional selection evidence. Field names, keys and existence are user-defined; no reserved field named Anwendung or special search checkbox is required.

Own entity-level descriptions from the identity header are also selection evidence; they need not be schema attributes. SWS exposes them as localized descriptions metadata. Use indexed text to find candidates when supported, then read their descriptions through sws-get-subject or sws-get-page-subjects. The stored-field fallback only filters schema statements and does not pretend identity descriptions are statement keys.
