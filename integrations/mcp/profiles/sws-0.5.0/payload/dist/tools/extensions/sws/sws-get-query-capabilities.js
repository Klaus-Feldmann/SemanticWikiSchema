import { z } from 'zod';
import { QueryCapabilities } from '../../../agent/queryCapabilities.js';
const inputSchema = {
    refresh: z
        .boolean()
        .optional()
        .describe('Repeat configuration discovery now instead of using the short-lived connection cache.'),
};
export const swsGetQueryCapabilities = {
    name: 'sws-get-query-capabilities',
    description: 'Reports configured, not-configured or unknown for the SWS Cypher/Neo4j and SPARQL query backends, using empty parser queries without database access. Enabled only with SemanticWikiSchema. Separately identifies stored-field and SMW query paths. Does not prove connectivity, OAuth grants, projection completeness or job completion. Unknown results are advisory, can be refreshed, and do not block requested read-only graph queries; a failed probe is never treated as missing configuration.',
    inputSchema,
    annotations: {
        title: 'Get SWS query capabilities',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    async handle({ refresh }, ctx) {
        const report = await (ctx.queryCapabilities ?? new QueryCapabilities()).inspect(ctx, refresh);
        const inspection = await ctx.wikiProbe.inspect(ctx.wiki.key);
        return ctx.format.ok({
            ...report,
            storedFields: {
                status: 'available-with-SWS',
                tool: 'sws-query-entities',
                queryKind: 'bounded paginated inspection; not an index',
            },
            smw: {
                status: inspection.extensions.has('SemanticMediaWiki')
                    ? 'extension-detected'
                    : 'not-detected',
                tool: 'smw-query',
                availabilityAndFreshness: 'A request and source read-back are still required.',
            },
            verification: 'sws-get-processing-status checks one known page/entity; its result does not establish global queue completion.',
        });
    },
};
