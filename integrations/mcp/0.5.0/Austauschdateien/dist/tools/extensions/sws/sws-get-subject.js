import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
import { flattenSubject } from './subjects.js';
const inputSchema = {
    latest: z
        .boolean()
        .optional()
        .describe('Read the current editable revision, including an unpublished revision visible to this user. Use before updates.'),
    id: z
        .string()
        .min(1)
        .describe('Persistent Subject ID (E-number, existing legacy s-ID, or sourceKey:localId). Use sws-search-subjects to resolve one from a label.'),
};
export const swsGetSubject = {
    name: 'sws-get-subject',
    description: 'Fetches one SWS Subject by ID — its label, display name, schema, the wiki page holding it, and statements with typed values. Enabled only when the wiki has SWS installed. Richer than a flattened Cypher node: it preserves multi-part values and per-statement types. `select` values are option IDs — decode them with sws-get-schema. A Dependent Subject has no label and carries hostSubject, the Subject it belongs to, and is named after its Schema and that Host Subject ("Birth of Pablo Picasso"). Get an ID from sws-search-subjects or a Cypher query. Pre-1.0: the SWS API may change without notice.',
    inputSchema,
    annotations: {
        title: 'Get SWS subject',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'get SWS subject',
    target: (a) => a.id,
    async handle({ id, latest }, ctx) {
        const mwn = await ctx.mwn();
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /subject response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'GET',
            path: `/subject/${encodeURIComponent(id)}`,
            query: { expand: 'page', ...(latest === true ? { latest: '1' } : {}) },
        }));
        const subject = data.subjects?.[id];
        if (subject === undefined) {
            return ctx.format.notFound(`SWS subject "${id}" not found`);
        }
        return ctx.format.ok(flattenSubject(subject, id));
    },
};
