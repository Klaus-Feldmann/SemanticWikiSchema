import { z } from 'zod';
import { resolvePageId, hasOnePageRef } from "../sws/pageId.js";
import { contentMaxBytes } from "../../../results/truncation.js";
const inputSchema = {
    title: z
        .string()
        .min(1)
        .optional()
        .describe('Wiki page title. Provide this OR pageId, not both.'),
    pageId: z
        .number()
        .int()
        .positive()
        .optional()
        .describe('Numeric page ID. Provide this OR title.'),
    subject: z.string().min(1).optional().describe('Restrict to this Subject on the selected page.'),
    language: z
        .string()
        .regex(/^[a-z]{2,12}(?:-[a-z0-9]{1,12})*$/)
        .optional()
        .describe('Data language, e.g. de or en. Omit to use the wiki API context language.'),
    fields: z
        .array(z.string().min(1))
        .min(1)
        .optional()
        .describe('Optional exact Schema field names to return. Omit for all fields. Useful for narrowing an oversized response.'),
};
// Validate the read-model envelope while preserving every typed field, value and source attribute.
const viewSchema = z
    .object({
    version: z.literal(1),
    readOnly: z.literal(true),
    page: z.object({ id: z.number().int().positive() }).passthrough(),
    subjects: z.array(z
        .object({ id: z.string(), fields: z.array(z.object({ name: z.string() }).passthrough()) })
        .passthrough()),
})
    .passthrough();
export const swsGetDataView = {
    name: 'swsbase-get-data-view',
    description: 'Reads the complete current SWS Data view: own and automatically retrieved values, effective-value precedence, source-qualified Q/P identities, descriptions, calendars, images and relations. Requires SWSBaseBridge with action=swsbasedataview (read-model version 1). Unlike sws-get-page-subjects this includes derived source values. It never stores fetched values, executes wikitext or writes SMW annotations. Preserve own/external distinctions; do not submit this read model to subject replacement APIs. An oversized response is refused explicitly; narrow by subject or exact field names. No silent truncation.',
    inputSchema,
    annotations: {
        title: 'Get complete SWS data view',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'get SWS data view',
    target: (a) => a.title ?? String(a.pageId ?? ''),
    async handle(args, ctx) {
        if (!hasOnePageRef(args))
            return ctx.format.invalidInput('Provide exactly one of title or pageId.');
        const mwn = await ctx.mwn();
        const pageId = await resolvePageId(mwn, args);
        if (pageId === null)
            return ctx.format.notFound(`Page "${args.title}" not found`);
        const response = await mwn.request({
            action: 'swsbasedataview',
            pageid: pageId,
            ...(args.subject === undefined ? {} : { subject: args.subject }),
            ...(args.language === undefined ? {} : { language: args.language }),
            format: 'json',
            formatversion: 2,
        });
        const envelope = z.object({ swsbasedataview: viewSchema }).safeParse(response);
        if (!envelope.success)
            return ctx.format.error('upstream_failure', 'SWSBaseBridge did not return data-view version 1. Check that the swsbasedataview API is installed and compatible.');
        const view = envelope.data.swsbasedataview;
        if (view.page.id !== pageId ||
            (args.subject !== undefined &&
                (view.subjects.length !== 1 || view.subjects[0]?.id !== args.subject))) {
            return ctx.format.error('upstream_failure', 'The data view does not match the requested page or Subject.');
        }
        if (args.fields !== undefined) {
            const names = new Set(args.fields);
            const available = new Set(view.subjects.flatMap((s) => s.fields.map((f) => f.name)));
            const missing = [...names].filter((n) => !available.has(n));
            if (missing.length)
                return ctx.format.invalidInput(`Unknown field names: ${missing.join(', ')}`);
            view.subjects = view.subjects.map((s) => ({
                ...s,
                fields: s.fields.filter((f) => names.has(f.name)),
            }));
        }
        const payload = {
            ...view,
            selection: { subject: args.subject ?? null, fields: args.fields ?? null },
        };
        const result = ctx.format.ok(payload);
        const bytes = Math.max(Buffer.byteLength(JSON.stringify(payload), 'utf8'), result.content.reduce((sum, c) => sum + (c.type === 'text' ? Buffer.byteLength(c.text, 'utf8') : 0), 0));
        if (bytes > contentMaxBytes())
            return ctx.format.invalidInput(`Data view exceeds the ${contentMaxBytes()}-byte response budget. Nothing was truncated. Retry with subject and/or exact fields; a single oversized field requires a larger MCP_CONTENT_MAX_BYTES setting.`);
        return result;
    },
};
