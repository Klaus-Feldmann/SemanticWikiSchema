import { z } from 'zod';
import { swsRequest } from "./swsRequest.js";
import { formatEditComment } from "../../../wikis/utils.js";
const inputSchema = {
    id: z
        .string()
        .min(1)
        .describe('Subject ID to delete (starts with s…). Resolve one with sws-search-subjects.'),
    comment: z.string().optional().describe('Optional edit summary.'),
};
export const swsDeleteSubject = {
    name: 'sws-delete-subject',
    description: 'Deletes one SWS Subject by ID from its page, together with its Dependent Subjects. Enabled only when the wiki has SWS installed. Relations that other Subjects hold to a standalone Subject stay, pointing at a Subject that no longer exists; deleting a Dependent Subject removes the relations to it, editing its Host Subject. Requires the edit right. Pre-1.0: the SWS API may change without notice.',
    inputSchema,
    annotations: {
        title: 'Delete SWS subject',
        readOnlyHint: false,
        destructiveHint: true,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'delete SWS subject',
    target: (a) => a.id,
    async handle({ id, comment }, ctx) {
        const mwn = await ctx.mwn();
        const editComment = formatEditComment(ctx, 'sws-delete-subject', comment);
        // The endpoint returns an empty 200 body on success; ignore it.
        await swsRequest(mwn, {
            method: 'DELETE',
            path: `/subject/${encodeURIComponent(id)}`,
            csrf: true,
            ...(editComment !== undefined ? { body: { comment: editComment } } : {}),
        });
        return ctx.format.ok({ subjectId: id, status: 'deleted' });
    },
};
