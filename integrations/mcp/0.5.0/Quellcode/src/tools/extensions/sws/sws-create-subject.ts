import { z } from 'zod';
import type { CallToolResult } from '@modelcontextprotocol/server';
import type { Tool } from '../../../runtime/tool.ts';
import type { ToolContext } from '../../../runtime/context.ts';
import { swsRequest } from './swsRequest.ts';
import { formatEditComment } from '../../../wikis/utils.ts';
import { resolvePageId, hasOnePageRef } from './pageId.ts';
import { writeResult, type SubjectWriteResponse } from './subjects.ts';
import {
	dependentSubjectsSchema,
	fieldNotesSchema,
	identityFields,
	statementsSchema,
} from './statements.ts';

const inputSchema = {
	...identityFields,
	id: z
		.string()
		.min(1)
		.optional()
		.describe('Unused Subject ID from sws-mint-subject-ids. Omit to let SWS allocate it.'),
	title: z
		.string()
		.min(1)
		.optional()
		.describe('Wiki page title to attach the Subject to. Provide this OR pageId.'),
	pageId: z
		.number()
		.int()
		.positive()
		.optional()
		.describe('Numeric MediaWiki page ID. Provide this OR title.'),
	isMain: z
		.boolean()
		.optional()
		.describe(
			"When true, create the page's Main Subject (at most one per page) instead of one of its other Subjects. Defaults to false.",
		),
	label: z
		.string()
		.optional()
		.describe(
			'Display label for the new Subject. Omit, or pass an empty string, for a Subject without one: a Main Subject is then shown under its page title, any other Subject under its Schema name.',
		),
	schema: z
		.string()
		.min(1)
		.describe(
			'Name of an existing Schema (entity type) the Subject instantiates. Discover names with sws-list-schemas.',
		),
	statements: statementsSchema.describe(
		'Map of property name → { propertyType, value }. Validate first with sws-validate-subject.',
	),
	fieldNotes: fieldNotesSchema
		.optional()
		.describe(
			'Author notes keyed by exact Schema field name, separate from statements. Omission preserves existing notes on update; a supplied map replaces all notes, and {} clears them. Plain text or Wikitext follows the Schema setting. Requires SWS 0.2.02 or later.',
		),
	dependentSubjects: dependentSubjectsSchema
		.optional()
		.describe(
			'Dependent Subjects to create in the same revision, each the target of a relation in statements or in another item of this list.',
		),
	comment: z.string().optional().describe('Optional edit summary.'),
} as const;

export const swsCreateSubject: Tool<typeof inputSchema> = {
	name: 'sws-create-subject',
	description:
		'Creates a new SWS Subject on an existing wiki page and returns its ID, the violations of its Schema it was saved with, and the Subject as saved. Enabled only when the wiki has SWS installed. Set isMain to make it the page\'s Main Subject (a page has at most one); otherwise it joins the page\'s other Subjects. Requires the edit right. A statement whose value is empty is dropped. A write that breaks a rule for Dependent Subjects is refused; other violations are warnings unless the wiki enforces validation, which refuses a write that introduces an error-severity one. A Dependent Subject, of a Schema with subjectsAreDependent in sws-get-schema, is created with the Subject that points at it: its ID comes from sws-mint-subject-ids, a relation targets it ({ target: "<id>" }), and the Dependent Subject goes in dependentSubjects. Field annotations use fieldNotes; omission preserves existing notes on update. Pre-validate statement values with sws-validate-subject. Pre-1.0: the SWS API may change without notice.',
	inputSchema,
	annotations: {
		title: 'Create SWS subject',
		readOnlyHint: false,
		destructiveHint: false,
		idempotentHint: false,
		openWorldHint: true,
	},
	failureVerb: 'create SWS subject',
	target: (a) => a.label || a.schema,

	async handle(
		{
			id,
			identityLanguage,
			labels,
			descriptions,
			title,
			pageId,
			isMain,
			label,
			schema,
			statements,
			fieldNotes,
			dependentSubjects,
			comment,
		},
		ctx: ToolContext,
	): Promise<CallToolResult> {
		if (!hasOnePageRef({ title, pageId })) {
			return ctx.format.invalidInput('Provide exactly one of title or pageId.');
		}

		const mwn = await ctx.mwn();
		const editComment = formatEditComment(ctx, 'sws-create-subject', comment);
		const language = identityLanguage ?? (await ctx.wikiProbe.inspect(ctx.wiki.key)).lang ?? 'en';
		const identity = {
			identityLanguage: language,
			labels: labels ?? (label !== undefined && label.trim() !== '' ? { [language]: label } : {}),
			descriptions: descriptions ?? {},
		};
		const resolvedPageId = await resolvePageId(mwn, { title, pageId });
		if (resolvedPageId === null) {
			return ctx.format.notFound(`Page "${title}" not found`);
		}

		const segment = isMain === true ? 'mainSubject' : 'subjects';
		// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS create response shape; trusted at this boundary
		const data = (await swsRequest(mwn, {
			method: 'POST',
			path: `/page/${resolvedPageId}/${segment}`,
			csrf: true,
			body: {
				...(id !== undefined ? { id } : {}),
				label,
				schema,
				...identity,
				statements,
				...(fieldNotes !== undefined ? { fieldNotes } : {}),
				dependentSubjects,
				...(editComment !== undefined ? { comment: editComment } : {}),
			},
		})) as SubjectWriteResponse;

		const subjectId = data.subjectId ?? '';
		return ctx.format.ok({
			subjectId,
			status: data.status ?? 'created',
			pageId: resolvedPageId,
			...writeResult(data, subjectId),
		});
	},
};
