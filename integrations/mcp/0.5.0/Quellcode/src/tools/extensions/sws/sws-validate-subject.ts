import { z } from 'zod';
import type { CallToolResult } from '@modelcontextprotocol/server';
import type { Tool } from '../../../runtime/tool.ts';
import type { ToolContext } from '../../../runtime/context.ts';
import { swsRequest } from './swsRequest.ts';
import { statementsSchema } from './statements.ts';

const inputSchema = {
	id: z
		.string()
		.min(1)
		.optional()
		.describe(
			'Subject ID to validate an UPDATE against (its schema is used). Omit to validate a NEW subject and pass schema instead.',
		),
	schema: z
		.string()
		.min(1)
		.optional()
		.describe(
			'Schema name to validate a NEW subject against. Required when id is omitted; ignored when id is given.',
		),
	statements: statementsSchema.describe(
		'Proposed statement map (property name → { propertyType, value }).',
	),
} as const;

interface ValidateResponse {
	violations?: unknown[];
}

export const swsValidateSubject: Tool<typeof inputSchema> = {
	name: 'sws-validate-subject',
	description:
		"Dry-runs validation of a proposed SWS Subject's statements against its Schema and returns any violations WITHOUT writing. Enabled only when the wiki has SWS installed. Provide id to validate an update to an existing Subject (its schema is used), or omit id and provide schema to validate a new Subject. Use before sws-create-subject / sws-update-subject to catch type mismatches, missing required properties, and the propertyType-vs-type footgun. Returns { violations: [] } when valid; each violation carries a stable code, the propertyName it concerns, and a severity of error or warning. The rules for Dependent Subjects are checked only when a write runs. Pre-1.0: the SWS API may change without notice.",
	inputSchema,
	annotations: {
		title: 'Validate SWS subject',
		readOnlyHint: true,
		destructiveHint: false,
		idempotentHint: true,
		openWorldHint: true,
	},
	failureVerb: 'validate SWS subject',
	target: (a) => a.id ?? a.schema ?? '',

	async handle({ id, schema, statements }, ctx: ToolContext): Promise<CallToolResult> {
		if (id === undefined && schema === undefined) {
			return ctx.format.invalidInput(
				'Provide schema to validate a new subject, or id to validate an update.',
			);
		}

		const mwn = await ctx.mwn();
		const spec =
			id !== undefined
				? { path: `/subject/${encodeURIComponent(id)}/validate`, body: { statements } }
				: { path: '/subject/validate', body: { schema, statements } };

		// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS validate response shape; trusted at this boundary
		const data = (await swsRequest(mwn, {
			method: 'POST',
			path: spec.path,
			body: spec.body,
		})) as ValidateResponse;

		return ctx.format.ok({ violations: Array.isArray(data.violations) ? data.violations : [] });
	},
};
