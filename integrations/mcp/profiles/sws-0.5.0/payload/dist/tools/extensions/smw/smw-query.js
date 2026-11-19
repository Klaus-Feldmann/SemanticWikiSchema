import { z } from 'zod';
import { capRowsByBytes } from '../../../results/truncation.js';
import { MALFORMED_CONTINUE_FROM, offsetFrom, rowPageTruncation, } from '../../../results/offsetCursor.js';
const HARD_LIMIT = 500;
const inputSchema = {
    query: z
        .string()
        .describe('SMW #ask query. Conditions: `[[Property::value]]`. Printouts: `|?Property`. ' +
        'Parameters: `|limit=N`, `|offset=N`, `|sort=Property`, `|order=asc/desc`.'),
    limit: z
        .number()
        .int()
        .min(1)
        .max(HARD_LIMIT)
        .optional()
        .describe('Overrides |limit= in query if both are set.'),
    continueFrom: z
        .string()
        .optional()
        .describe('Opaque continuation token from a previous response; omit on first call.'),
};
export const smwQuery = {
    name: 'smw-query',
    description: "Runs a Semantic MediaWiki `#ask` query against the targeted wiki. Enabled only when the wiki has SMW installed. One row per matching page, with the requested printouts as columns. For grounded property names, use smw-list-properties first.\n\nExamples:\n- Pages in a category: [[Category:Person]]|?Has occupation|limit=20\n- Numeric comparison: [[Born in::>1900]]|?Has name|?Born in\n- Multiple conditions: [[Category:Person]][[Born in::>1900]][[Has occupation::Architect]]\n\nNumeric operators: write `>N` and `<N`; SMW's default config treats them as ≥ and ≤. `>=` and `<=` are rejected unless the wiki enables strict comparators (`$smwStrictComparators`).\n\nUp to 500 rows per call, and only the whole rows that fit in 75000 bytes by default; paginate with continueFrom. Syntax errors return the SMW message verbatim.",
    inputSchema,
    annotations: {
        title: 'Run SMW query',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'run SMW query',
    target: (a) => a.query,
    async handle({ query, limit, continueFrom }, ctx) {
        const offset = offsetFrom(continueFrom);
        if (offset === undefined) {
            return ctx.format.invalidInput(MALFORMED_CONTINUE_FROM);
        }
        const merged = mergeQueryParams(query, limit, offset);
        const mwn = await ctx.mwn();
        const raw = await mwn.request({
            action: 'ask',
            query: merged,
            format: 'json',
        });
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SMW action=ask response shape; trusted at this boundary
        const response = raw;
        const errors = response.query?.errors;
        if (Array.isArray(errors) && errors.length > 0) {
            return ctx.format.invalidInput(errors.join('; '));
        }
        const resultsMap = response.query?.results ?? {};
        const rows = Object.entries(resultsMap).map(([title, row]) => normalizeRow(title, row));
        const capped = capRowsByBytes(rows);
        // SMW returns query-continue-offset as a JSON number per the action=ask docs.
        // If it ever comes back as a string from a misbehaving proxy, pagination
        // silently stops; callers will see a complete-looking result.
        const continueOffset = response['query-continue-offset'];
        return ctx.format.ok({
            rows: capped.rows,
            dataFreshness: 'unknown',
            verification: 'SMW annotations can lag behind SWS saves while jobs run. An empty result does not prove absence; verify a known candidate with SWS read-back and sws-get-processing-status.',
            ...rowPageTruncation(capped, {
                toolName: 'smw-query',
                offset,
                nextOffset: typeof continueOffset === 'number' ? continueOffset : undefined,
                narrowing: 'request fewer printouts',
            }),
        });
    },
};
function normalizeRow(title, row) {
    const printouts = {};
    for (const [key, value] of Object.entries(row.printouts ?? {})) {
        if (Array.isArray(value) && value.length > 0) {
            printouts[key] = value;
        }
    }
    return {
        title: row.fulltext ?? title,
        ...(typeof row.pageid === 'number' ? { pageId: row.pageid } : {}),
        namespace: typeof row.namespace === 'number' ? row.namespace : 0,
        printouts,
    };
}
function mergeQueryParams(rawQuery, schemaLimit, offset) {
    // Strip any existing |limit= and |offset= parameters out of the user's query
    // so we can re-append our normalized values at the end. Last-wins isn't
    // portable across SMW versions, so we explicitly remove + reappend.
    const stripped = rawQuery
        .split('|')
        .filter((segment, index) => {
        if (index === 0) {
            return true; // conditions
        }
        const trimmed = segment.trim();
        return !/^limit\s*=/i.test(trimmed) && !/^offset\s*=/i.test(trimmed);
    })
        .join('|');
    const embeddedLimitMatch = rawQuery.match(/\|\s*limit\s*=\s*(\d+)/i);
    const embeddedLimit = embeddedLimitMatch ? Number.parseInt(embeddedLimitMatch[1], 10) : undefined;
    let effectiveLimit;
    if (typeof schemaLimit === 'number') {
        effectiveLimit = Math.min(schemaLimit, HARD_LIMIT);
    }
    else if (typeof embeddedLimit === 'number' && Number.isFinite(embeddedLimit)) {
        effectiveLimit = Math.min(embeddedLimit, HARD_LIMIT);
    }
    else {
        effectiveLimit = HARD_LIMIT;
    }
    const parts = [stripped, `limit=${effectiveLimit}`];
    if (offset > 0) {
        parts.push(`offset=${offset}`);
    }
    return parts.join('|');
}
