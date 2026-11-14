import { z } from 'zod';
import type { CallToolResult } from '@modelcontextprotocol/server';
import type { Tool } from '../../../runtime/tool.ts';
import type { ToolContext } from '../../../runtime/context.ts';
import type { TruncationInfo } from '../../../results/truncation.ts';
import { swsRequest } from './swsRequest.ts';

// The /subject-labels endpoint caps `limit` at 50 and has no continuation.
const RESULT_LIMIT = 50;

const inputSchema = {
	schema: z
		.string()
		.min(1)
		.optional()
		.describe(
			'Limits matches to Subjects of this Schema, named exactly, with matching case. Omit to search every Schema. Use sws-list-schemas to discover names.',
		),
	search: z.string().min(1).describe('Name prefix to match, case-insensitively.'),
} as const;

interface SubjectLabel {
	id?: string;
	label?: string;
	pageTitle?: string;
}

export const swsSearchSubjects: Tool<typeof inputSchema> = {
	name: 'sws-search-subjects',
	description:
		"Finds SWS Subjects whose name starts with the given text, returning each match's persistent Subject ID, its name as `label`, and the page title of the wiki page holding it. Enabled only when the wiki has SWS installed. A Subject's name is its label, or for a Main Subject without one, its page title; Dependent Subjects and other Subjects without a label are not found. Subject IDs are not human-readable, so use this to resolve a name to an ID for sws-get-subject or a parameterized sws-cypher-query. Returns at most 50 matches. Pre-1.0: the SWS API may change without notice.",
	inputSchema,
	annotations: {
		title: 'Search SWS subjects',
		readOnlyHint: true,
		destructiveHint: false,
		idempotentHint: true,
		openWorldHint: true,
	},
	failureVerb: 'search SWS subjects',
	target: (a) => a.search,

	async handle({ schema, search }, ctx: ToolContext): Promise<CallToolResult> {
		const mwn = await ctx.mwn();
		// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /subject-labels response shape; trusted at this boundary
		const data = (await swsRequest(mwn, {
			method: 'GET',
			path: '/subject-labels',
			query: {
				search,
				limit: String(RESULT_LIMIT),
				...(schema !== undefined ? { schema } : {}),
			},
		})) as SubjectLabel[];

		const subjects = Array.isArray(data) ? data : [];
		const truncation: TruncationInfo | null =
			subjects.length >= RESULT_LIMIT
				? {
						reason: 'capped-no-continuation',
						returnedCount: subjects.length,
						limit: RESULT_LIMIT,
						itemNoun: 'subjects',
						narrowHint: 'lengthen the search prefix or pass a schema',
					}
				: null;

		return ctx.format.ok({ subjects, ...(truncation !== null ? { truncation } : {}) });
	},
};
