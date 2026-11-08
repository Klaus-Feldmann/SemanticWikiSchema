import { z } from 'zod';
import { swsRequest } from "./swsRequest.js";
import { flattenSubject } from "./subjects.js";
import { resolvePageId, hasOnePageRef } from "./pageId.js";
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
        .describe('Numeric MediaWiki page ID. Provide this OR title, not both.'),
};
export const swsGetPageSubjects = {
    name: 'sws-get-page-subjects',
    description: "Lists the SWS Subjects attached to a wiki page — each with full structured data — and identifies the page's Main Subject. Enabled only when the wiki has SWS installed. This is the bridge from a wiki page (which you may already have from get-page) into its knowledge-graph data: one call returns every subject's statements, so you don't need to resolve subject IDs first. Dependent Subjects are listed with the hostSubject they belong to. Accepts a page title or a numeric page ID. Pre-1.0: the SWS API may change without notice.",
    inputSchema,
    annotations: {
        title: 'Get SWS page subjects',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'get SWS page subjects',
    target: (a) => a.title ?? (a.pageId !== undefined ? String(a.pageId) : ''),
    async handle({ title, pageId }, ctx) {
        if (!hasOnePageRef({ title, pageId })) {
            return ctx.format.invalidInput('Provide exactly one of title or pageId.');
        }
        const mwn = await ctx.mwn();
        const resolvedPageId = await resolvePageId(mwn, { title, pageId });
        if (resolvedPageId === null) {
            return ctx.format.notFound(`Page "${title}" not found`);
        }
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /page/{id}/subjects response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'GET',
            path: `/page/${resolvedPageId}/subjects`,
        }));
        const mainSubjectId = data.mainSubjectId ?? null;
        const subjects = Object.entries(data.subjects ?? {}).map(([id, subject]) => ({
            ...flattenSubject(subject, id),
            isMain: id === mainSubjectId,
        }));
        return ctx.format.ok({
            pageId: data.pageId ?? resolvedPageId,
            mainSubjectId,
            subjects,
        });
    },
};
