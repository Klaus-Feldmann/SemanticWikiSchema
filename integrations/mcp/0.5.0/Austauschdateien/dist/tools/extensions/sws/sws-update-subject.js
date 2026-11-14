import { z } from 'zod';
import { updateIdentity } from './writeIdentity.js';
import { swsRequest } from './swsRequest.js';
import { formatEditComment } from '../../../wikis/utils.js';
import { writeResult } from './subjects.js';
import { dependentSubjectsSchema, fieldNotesSchema, identityFields, statementsSchema, } from './statements.js';
const inputSchema = {
    ...identityFields,
    fieldKeyVersion: z
        .number()
        .int()
        .nonnegative()
        .optional()
        .describe('Field-key version returned by sws-get-subject. Pass it back when replacing statements after a field rename.'),
    id: z
        .string()
        .min(1)
        .describe('Subject ID to replace (starts with s…). Resolve one with sws-search-subjects.'),
    label: z
        .string()
        .optional()
        .describe('New display label. Omit it, or pass an empty string, to clear the label, as a Dependent Subject (one with hostSubject) requires.'),
    statements: statementsSchema.describe('Full replacement statement map (property name → { propertyType, value }). Property names absent from this map are deleted; pass {} to clear all statements.'),
    fieldNotes: fieldNotesSchema
        .optional()
        .describe('Author notes keyed by exact Schema field name, separate from statements. Omission preserves existing notes on update; a supplied map replaces all notes, and {} clears them. Plain text or Wikitext follows the Schema setting. Requires SWS 0.2.02 or later.'),
    dependentSubjects: dependentSubjectsSchema
        .optional()
        .describe('Dependent Subjects to create or replace in the same revision, each the target of a relation in statements or in another item of this list.'),
    comment: z.string().optional().describe('Optional edit summary.'),
};
export const swsUpdateSubject = {
    name: 'sws-update-subject',
    description: "Replaces a SWS Subject's label and statements — a FULL replace: an omitted or empty label is cleared, property names absent from `statements` are deleted, and {} clears all statements. Returns the violations of its Schema the Subject was saved with, and the Subject as saved. Enabled only when the wiki has SWS installed. The Subject's id, schema and page are immutable. Requires the edit right. Read the current state with sws-get-subject (latest=true) first and pass back fieldKeyVersion if returned. Dependent Subjects are written through dependentSubjects as with sws-create-subject, and passing the ID of one this Subject reaches replaces it; dropping the last relation to a Dependent Subject deletes it. A write that breaks a rule for Dependent Subjects is refused; other violations are warnings unless the wiki enforces validation, which refuses a write that introduces an error-severity one. Field annotations use fieldNotes; omission preserves existing notes on update. Pre-validate statement values with sws-validate-subject. Pre-1.0: the SWS API may change without notice.",
    inputSchema,
    annotations: {
        title: 'Update SWS subject',
        readOnlyHint: false,
        destructiveHint: true,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'update SWS subject',
    target: (a) => a.id,
    async handle({ id, identityLanguage, labels, descriptions, fieldKeyVersion, label, statements, fieldNotes, dependentSubjects, comment, }, ctx) {
        const mwn = await ctx.mwn();
        const editComment = formatEditComment(ctx, 'sws-update-subject', comment);
        const identity = await updateIdentity(mwn, id, { identityLanguage, labels, descriptions }, label);
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS replace response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'PUT',
            path: `/subject/${encodeURIComponent(id)}`,
            csrf: true,
            body: {
                ...(fieldKeyVersion !== undefined ? { fieldKeyVersion } : {}),
                label,
                ...identity,
                statements,
                ...(fieldNotes !== undefined ? { fieldNotes } : {}),
                dependentSubjects,
                ...(editComment !== undefined ? { comment: editComment } : {}),
            },
        }));
        const subjectId = data.subjectId ?? id;
        return ctx.format.ok({
            subjectId,
            status: data.status ?? 'updated',
            ...writeResult(data, subjectId),
        });
    },
};
