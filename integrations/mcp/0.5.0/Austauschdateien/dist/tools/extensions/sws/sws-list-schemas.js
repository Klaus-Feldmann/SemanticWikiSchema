import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
// The /schemas endpoint caps `limit` at 50 (rejects higher with a 400).
const PAGE_LIMIT = 50;
const inputSchema = {
    continueFrom: z
        .string()
        .min(1)
        .optional()
        .describe('Opaque cursor from a previous response; omit on first call.'),
};
export const swsListSchemas = {
    name: 'sws-list-schemas',
    description: "Lists the Schemas (entity types, e.g. Person, Company) defined in the wiki's SWS knowledge graph, each with its description and property count; subjectsAreDependent is present, as true, only on a Schema whose Subjects are Dependent Subjects. Enabled only when the wiki has SWS installed. Start here to discover what types exist, then use sws-get-schema for one type's properties, or sws-cypher-query to query the graph. Paginate with continueFrom. Pre-1.0: the SWS API may change without notice.",
    inputSchema,
    annotations: {
        title: 'List SWS schemas',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'list SWS schemas',
    async handle({ continueFrom }, ctx) {
        const mwn = await ctx.mwn();
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /schemas response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'GET',
            path: '/schemas',
            query: {
                limit: String(PAGE_LIMIT),
                ...(continueFrom !== undefined ? { cursor: continueFrom } : {}),
            },
        }));
        const schemas = (Array.isArray(data.schemas) ? data.schemas : []).map(({ subjectsAreDependent, ...summary }) => subjectsAreDependent === true ? { ...summary, subjectsAreDependent } : summary);
        const truncation = typeof data.nextCursor === 'string'
            ? {
                reason: 'more-available',
                returnedCount: schemas.length,
                itemNoun: 'schemas',
                toolName: 'sws-list-schemas',
                continueWith: { param: 'continueFrom', value: data.nextCursor },
            }
            : null;
        return ctx.format.ok({ schemas, ...(truncation !== null ? { truncation } : {}) });
    },
};
