import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
const inputSchema = {
    cypher: z
        .string()
        .min(1)
        .describe('A single read-only Cypher statement. Backtick-escape names with spaces, and names that are Cypher keywords such as Show: s.`Birth year`, -[:`Main product`]->.'),
    parameters: z
        .record(z.string(), z.unknown())
        .optional()
        .describe('Parameter map referenced as $name in the query. Prefer this over string concatenation.'),
};
export const swsCypherQuery = {
    name: 'sws-cypher-query',
    description: "Runs a read-only Cypher query against the wiki's SWS knowledge graph (Neo4j) and returns rows. Enabled only when the wiki has SWS installed; the query endpoint exists only where SWS has a Neo4j backend.\n\nDiscover the data model first — the graph CANNOT be introspected from Cypher (CALL db.labels(), db.schema.* and all procedure calls are rejected as not-read-only). Use sws-list-schemas for the entity types, then sws-get-schema for a type's properties and relations.\n\nGraph model:\n- Subjects are nodes labelled `:Subject` plus a per-schema label, e.g. (s:Subject:Person). Subject node properties are the schema's properties plus `id` and `name`. `name` is the Subject's label, or a Main Subject's page title when it has no label, and is absent otherwise.\n- Pages are (:Page) nodes linked by (:Page)-[:HasSubject {isMain, isDependent}]->(:Subject). Subject-to-subject relations are edges typed by the relation property's name (see sws-get-schema): (c:Subject:Company)-[:`Main product`]->(p:Subject:Product).\n- A node labelled only `:Subject` is a stub: the target of a relation whose Subject was deleted or not yet created.\n- Statement values are lists, except `number` and `boolean` values, which are single: match with 'Germany' IN s.Country, not s.Country = 'Germany'. `select` parts are opaque option IDs (e.g. o1abc…), not labels — decode them with sws-get-schema. `date` and `dateTime` parts are Neo4j dates and datetimes; `monolingualText` parts are `text@language` strings.\n\nGotchas:\n- Whole nodes come back as { id, labels, properties }, where id is Neo4j's internal node ID and the Subject ID is properties.id; projecting the properties needed keeps rows small.\n- Plain list values (labels(n), collect(...)) come back as 1-indexed JSON objects ({\"1\":…,\"2\":…}), not arrays.\n- Read-only only; write clauses are rejected.\n\nLimits: ~5,000 rows / 30s by default (50,000 / 300s with the apihighlimits right). When `truncated` is true, narrow with WHERE or add LIMIT/SKIP. Pre-1.0: the SWS API may change without notice.\n\nExample:\n  cypher: MATCH (s:Subject:Person) WHERE s.`Birth year` > $minYear RETURN s.name AS name, s.`Birth year` AS year ORDER BY year\n  parameters: { \"minYear\": 2000 }",
    inputSchema,
    annotations: {
        title: 'Run SWS Cypher query',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'run SWS Cypher query',
    target: (a) => a.cypher,
    async handle({ cypher, parameters }, ctx) {
        const mwn = await ctx.mwn();
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /query/cypher response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'POST',
            path: '/query/cypher',
            body: { cypher, ...(parameters !== undefined ? { parameters } : {}) },
        }));
        const columns = Array.isArray(data.columns) ? data.columns : [];
        const rows = Array.isArray(data.rows) ? data.rows : [];
        const truncation = data.truncated === true
            ? {
                reason: 'capped-no-continuation',
                returnedCount: rows.length,
                limit: rows.length,
                itemNoun: 'rows',
                narrowHint: 'narrow with WHERE, add LIMIT/SKIP to paginate, or use an account with the apihighlimits right for a higher cap.',
            }
            : null;
        return ctx.format.ok({
            columns,
            rows,
            resultCount: typeof data.resultCount === 'number' ? data.resultCount : rows.length,
            ...(typeof data.durationMs === 'number' ? { durationMs: data.durationMs } : {}),
            ...(truncation !== null ? { truncation } : {}),
        });
    },
};
