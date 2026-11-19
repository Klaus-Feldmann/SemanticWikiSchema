import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
import { contentMaxBytes } from '../../../results/truncation.js';
const inputSchema = {
    query: z
        .string()
        .min(1)
        .describe('Read-only SPARQL SELECT or ASK query for the configured SWS store, with explicit limits for SELECT. Determine actual schema/field RDF mappings first.'),
};
const resultSchema = z.union([
    z
        .object({
        // PHP associative decoding turns the W3C empty head object into [] on re-encoding.
        head: z.preprocess((value) => (Array.isArray(value) && value.length === 0 ? {} : value), z.object({ vars: z.array(z.string()).optional() }).passthrough()),
        boolean: z.boolean(),
    })
        .passthrough(),
    z
        .object({
        head: z.object({ vars: z.array(z.string()) }).passthrough(),
        results: z.object({ bindings: z.array(z.record(z.string(), z.unknown())) }).passthrough(),
    })
        .passthrough(),
]);
export const swsSparqlQuery = {
    name: 'sws-sparql-query',
    description: 'Runs read-only SPARQL SELECT or ASK through SWS against its first configured SPARQL store. Enabled with SemanticWikiSchema and hidden after a missing store is detected. sws-get-query-capabilities distinguishes configuration from connectivity and freshness. Returns a W3C result document inside document, preserving binding metadata. This is distinct from wikibase-query: it targets SWS RDF, not a Wikibase query service. Oversized results are refused rather than silently truncated; narrow with LIMIT and filters. For queries over schema fields, determine their actual RDF projection first; ordinary full-text/category searches need no graph/schema preflight. Unknown backend configuration is advisory, not a call prohibition.',
    inputSchema,
    annotations: {
        title: 'Query SWS SPARQL store',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    async handle({ query }, ctx) {
        // SWS's read-only query endpoint is the authoritative SPARQL grammar validator.
        // Restrict the outer operation too; reject updates and result formats this tool cannot return.
        let leading = query;
        const prefix = /^(?:\s+|#[^\r\n]*(?:\r?\n|$)|PREFIX\s+[\w-]*:\s*<[^>]+>|BASE\s*<[^>]+>)/i;
        let token;
        while ((token = leading.match(prefix)) && token[0].length > 0)
            leading = leading.slice(token[0].length);
        if (!/^(SELECT|ASK)\b/i.test(leading))
            return ctx.format.invalidInput('Only SPARQL SELECT and ASK are supported; updates are not sent.');
        const raw = await swsRequest(await ctx.mwn(), {
            method: 'POST',
            path: '/query/sparql',
            body: { query },
        });
        const emptyArrayHead = raw !== null &&
            typeof raw === 'object' &&
            'head' in raw &&
            Array.isArray(raw.head) &&
            raw.head.length === 0;
        const document = resultSchema.safeParse(raw);
        if (!document.success)
            return ctx.format.error('upstream_failure', `SWS returned an invalid SPARQL result document: ${document.error.message}`);
        if (Buffer.byteLength(JSON.stringify(document.data)) > contentMaxBytes())
            return ctx.format.invalidInput('SPARQL result exceeds the response budget. No partial document is returned; narrow the query.');
        return ctx.format.ok({
            document: document.data,
            ...(emptyArrayHead
                ? {
                    normalizations: [
                        'Empty ASK head array converted to the W3C empty head object; bindings and boolean value unchanged.',
                    ],
                }
                : {}),
            dataFreshness: 'unknown',
            verification: 'A successful graph query does not establish that runJobs or RDF delivery has caught up.',
        });
    },
};
