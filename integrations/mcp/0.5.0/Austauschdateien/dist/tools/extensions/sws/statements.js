import { z } from 'zod';
const VALUE_FORMATS = 'text/url/date/dateTime → array of strings (date "2020-12-31", dateTime "2020-12-31T14:30:00Z", with Z or an offset); select → array of option IDs (resolve labels via sws-get-schema); number → a number; boolean → a boolean; relation → array of { target: "<subjectId>" }; monolingualText → array of { text, language }';
// The write API reads `propertyType` (NOT the `type` key read tools return) and
// silently drops entries without it, so require it here — a malformed statement
// becomes an input error, not a no-op.
const statementSchema = z.object({
    propertyType: z
        .string()
        .min(1)
        .describe("The property's type. The key is propertyType, NOT the `type` key read tools return."),
    value: z.unknown().describe(`Value shaped by propertyType: ${VALUE_FORMATS}.`),
});
export const statementsSchema = z.record(z.string(), statementSchema);
export const fieldNotesSchema = z.record(z.string().min(1), z.string());
export const identityFields = {
    identityLanguage: z
        .string()
        .min(1)
        .optional()
        .describe('Language code of the own default identity, e.g. de.'),
    labels: z
        .record(z.string().min(1), z.string())
        .optional()
        .describe('Own labels keyed by language code. Omit to preserve existing labels on update.'),
    descriptions: z
        .record(z.string().min(1), z.string())
        .optional()
        .describe('Own descriptions keyed by language code. Omit to preserve existing descriptions on update.'),
};
export const dependentSubjectsSchema = z.array(z.object({
    id: z
        .string()
        .min(1)
        .describe('An unused Subject ID from sws-mint-subject-ids, creating the Dependent Subject, or the ID of a Dependent Subject this Subject reaches, replacing it.'),
    ...identityFields,
    fieldKeyVersion: z
        .number()
        .int()
        .nonnegative()
        .optional()
        .describe('Field-key version returned by reading this Dependent Subject; pass it back on replacement.'),
    schema: z.string().min(1).describe('Name of a Schema whose Subjects are dependent.'),
    fieldNotes: fieldNotesSchema
        .optional()
        .describe('Author notes keyed by exact Schema field name. Omit to preserve existing notes; {} clears them. Requires SWS 0.2.02 or later.'),
    statements: statementsSchema.describe("The Dependent Subject's statement map, shaped as for the Subject itself."),
}));
