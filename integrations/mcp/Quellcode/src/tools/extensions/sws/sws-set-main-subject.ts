import { z } from 'zod';
import type { CallToolResult } from '@modelcontextprotocol/server';
import type { Tool } from '../../../runtime/tool.ts';
import type { ToolContext } from '../../../runtime/context.ts';
import { swsRequest } from './swsRequest.ts';
import { formatEditComment } from '../../../wikis/utils.ts';
import { resolvePageId, hasOnePageRef } from './pageId.ts';

const inputSchema = {
	title: z.string().min(1).optional().describe('Wiki page title. Provide this OR pageId.'),
	pageId: z
		.number()
		.int()
		.positive()
		.optional()
		.describe('Numeric MediaWiki page ID. Provide this OR title.'),
	subjectId: z
		.string()
		.min(1)
		.nullable()
		.describe(
			'Subject ID (starts with s…) on this page to promote to Main Subject, or null to clear the Main Subject.',
		),
	comment: z.string().optional().describe('Optional edit summary.'),
} as const;

interface SetMainResponse {
	status?: string;
}

export const swsSetMainSubject: Tool<typeof inputSchema> = {
	name: 'sws-set-main-subject',
	description:
		"Sets which existing Subject on a wiki page is its Main Subject, or clears it. Enabled only when the wiki has SWS installed. Pass subjectId to promote one of the page's other Subjects, or null to clear the Main Subject. A Dependent Subject cannot be a Main Subject. This differs from sws-create-subject with isMain, which creates a NEW main Subject. Requires the edit right. Pre-1.0: the SWS API may change without notice.",
	inputSchema,
	annotations: {
		title: 'Set SWS main subject',
		readOnlyHint: false,
		destructiveHint: false,
		idempotentHint: true,
		openWorldHint: true,
	},
	failureVerb: 'set SWS main subject',
	target: (a) => a.subjectId ?? '',

	async handle({ title, pageId, subjectId, comment }, ctx: ToolContext): Promise<CallToolResult> {
		if (!hasOnePageRef({ title, pageId })) {
			return ctx.format.invalidInput('Provide exactly one of title or pageId.');
		}

		const mwn = await ctx.mwn();
		const editComment = formatEditComment(ctx, 'sws-set-main-subject', comment);
		const resolvedPageId = await resolvePageId(mwn, { title, pageId });
		if (resolvedPageId === null) {
			return ctx.format.notFound(`Page "${title}" not found`);
		}

		// Always send the subjectId key: omitting it is a 400 upstream, whereas
		// an explicit null is the documented way to clear the Main Subject.
		// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS set-main response shape; trusted at this boundary
		const data = (await swsRequest(mwn, {
			method: 'PUT',
			path: `/page/${resolvedPageId}/mainSubject`,
			csrf: true,
			body: { subjectId, ...(editComment !== undefined ? { comment: editComment } : {}) },
		})) as SetMainResponse;

		return ctx.format.ok({ pageId: resolvedPageId, status: data.status ?? 'changed' });
	},
};
