import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
import { flattenSubject } from './subjects.js';
import { contentMaxBytes } from '../../../results/truncation.js';
const filterSchema = z.object({
    field: z.string().min(1).describe('Exact field key from sws-get-schema; not a display label.'),
    operator: z
        .enum(['equals', 'contains'])
        .describe('Exact primitive comparison or case-insensitive text containment.'),
    value: z
        .union([z.string(), z.number(), z.boolean()])
        .describe('Value to match; select fields use option IDs, not labels.'),
});
const inputSchema = {
    schema: z.string().min(1).describe('Schema name discovered with sws-list-schemas.'),
    filters: z
        .array(filterSchema)
        .max(8)
        .default([])
        .describe('AND-combined filters on stored field values.'),
    fields: z
        .array(z.string().min(1))
        .max(12)
        .default([])
        .describe('Field keys to return; omitted keys return only entity/page identifiers.'),
    limit: z
        .number()
        .int()
        .min(1)
        .max(25)
        .default(10)
        .describe('Maximum schema members inspected in this call, including non-matches.'),
    continueFrom: z
        .string()
        .min(1)
        .optional()
        .describe('Opaque nextCursor from the previous call with the same schema and filters.'),
};
// Search value parts, not arbitrary object metadata or an accidental JSON string.
export function fieldParts(value) {
    if (typeof value === 'string' || typeof value === 'number' || typeof value === 'boolean')
        return [value];
    if (Array.isArray(value))
        return value.flatMap(fieldParts);
    if (value && typeof value === 'object') {
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- non-null object narrowed to inspect allowed value fields
        const record = value;
        return ['text', 'url', 'label'].flatMap((key) => fieldParts(record[key]));
    }
    return [];
}
export const swsQueryEntities = {
    name: 'sws-query-entities',
    description: 'Finds SWS entity candidates by stored schema field values, including purpose and class. Enabled only when SemanticWikiSchema is installed. Discover schema names and field keys/select option IDs with sws-list-schemas and sws-get-schema. This portable fallback inspects one bounded page of schema members, not an indexed full-catalogue search. Follow nextCursor even when a page contains no matches; complete means the listing ended. It does not read template source or derived/external data-view fields. For configured graph queries, sws-cypher-query remains available.',
    inputSchema,
    annotations: {
        title: 'Query SWS entity fields',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    async handle({ schema, filters, fields, limit, continueFrom }, ctx) {
        const mwn = await ctx.mwn();
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- trusted SWS read API response boundary
        const schemaResponse = (await swsRequest(mwn, {
            method: 'GET',
            path: `/schema/${encodeURIComponent(schema)}`,
        }));
        if (!schemaResponse.schema)
            return ctx.format.notFound(`SWS schema "${schema}" not found`);
        const definitions = schemaResponse.schema.propertyDefinitions ?? {};
        for (const key of [...fields, ...filters.map((filter) => filter.field)]) {
            if (!Object.hasOwn(definitions, key))
                return ctx.format.invalidInput(`Unknown field key "${key}"; read sws-get-schema first.`);
        }
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- trusted SWS read API response boundary
        const listing = (await swsRequest(mwn, {
            method: 'GET',
            path: '/subjects',
            query: {
                schema,
                sort: 'page',
                direction: 'asc',
                limit: String(limit),
                ...(continueFrom ? { cursor: continueFrom } : {}),
            },
        }));
        if (!Array.isArray(listing.subjects) ||
            listing.subjects.length > limit ||
            listing.subjects.some((item) => typeof item.id !== 'string' || item.id.length === 0))
            throw new Error('Invalid SWS entity listing response.');
        const matches = [];
        const unverified = [];
        let bytes = 0;
        for (const summary of listing.subjects) {
            // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- trusted SWS read API response boundary
            const response = (await swsRequest(mwn, {
                method: 'GET',
                path: `/subject/${encodeURIComponent(summary.id)}`,
                query: { expand: 'page' },
            }));
            const subject = response.subjects?.[summary.id];
            if (!subject) {
                unverified.push({
                    id: summary.id,
                    reason: 'No readable entity returned; listing or permissions changed.',
                });
                continue;
            }
            const schemaIdentity = typeof subject.schema === 'string' ? subject.schema : subject.schema?.id;
            if (typeof schemaIdentity !== 'string' ||
                (schemaIdentity !== schema && schemaIdentity !== schemaResponse.schema.id)) {
                unverified.push({
                    id: summary.id,
                    reason: 'Entity schema changed or could not be verified.',
                });
                continue;
            }
            if (!filters.every((filter) => fieldParts(subject.statements?.[filter.field]?.value).some((part) => filter.operator === 'equals'
                ? part === filter.value
                : typeof part === 'string' &&
                    typeof filter.value === 'string' &&
                    part.toLocaleLowerCase().includes(filter.value.toLocaleLowerCase()))))
                continue;
            const selected = Object.fromEntries(Object.entries(subject.statements ?? {}).filter(([key]) => fields.includes(key)));
            const entity = flattenSubject({ ...subject, statements: selected, fieldNotes: undefined }, summary.id);
            bytes += Buffer.byteLength(JSON.stringify(entity));
            if (bytes > contentMaxBytes()) {
                return ctx.format.invalidInput('Matching fields exceed the response budget. Repeat this page with a smaller limit or fewer returned fields; no partial match list is emitted.');
            }
            matches.push(entity);
        }
        const nextCursor = listing.nextCursor ?? null;
        return ctx.format.ok({
            provider: 'sws-stored-fields-paginated',
            dataFreshness: 'unknown',
            verification: 'Listing exhaustion does not prove that runJobs/indexes have caught up. For a known page/entity, read it directly and inspect sws-get-processing-status before concluding that data is absent.',
            schema,
            matches,
            inspected: listing.subjects.length,
            unverified,
            nextCursor,
            complete: nextCursor === null && unverified.length === 0,
            coverage: nextCursor !== null
                ? 'partial; continue even if matches is empty'
                : unverified.length
                    ? 'listing ended with unverified entities'
                    : 'listing ended; earlier pages must also have been inspected',
            ...(nextCursor
                ? {
                    truncation: {
                        reason: 'more-available',
                        returnedCount: matches.length,
                        itemNoun: 'matching entities',
                        toolName: 'sws-query-entities',
                        continueWith: { param: 'continueFrom', value: nextCursor },
                    },
                }
                : {}),
        });
    },
};
