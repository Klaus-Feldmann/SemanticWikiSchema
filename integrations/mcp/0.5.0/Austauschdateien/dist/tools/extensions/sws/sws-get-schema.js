import { z } from 'zod';
import { swsRequest } from './swsRequest.js';
const inputSchema = {
    name: z.string().min(1).describe('Schema name. Use sws-list-schemas to discover names.'),
};
export const swsGetSchema = {
    name: 'sws-get-schema',
    description: "Returns one SWS Schema's property definitions: each property's name, type (text/number/boolean/url/date/dateTime/select/relation/monolingualText), and type-specific attributes. Enabled only when the wiki has SWS installed. A `relation` property's name is its graph edge type, and `targetSchemas` lists the Schemas its targets follow — this is the relationship vocabulary for sws-cypher-query. `select` properties carry `options`, the list of { id, label } needed to decode select values returned by queries. A constraint (`required`, `minimum`, `options`, …) is a bare value, or an object carrying its severity: { value, severity }, or { severity } for a boolean constraint that is true; it only warns unless its severity is `error` and the wiki enforces validation. `subjectsAreDependent: true` marks a Schema whose Subjects are Dependent Subjects: unlabelled, never a page's Main Subject, each reached through one relation from its Host Subject on the same page. Use sws-list-schemas to discover schema names. Pre-1.0: the SWS API may change without notice.",
    inputSchema,
    annotations: {
        title: 'Get SWS schema',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'get SWS schema',
    target: (a) => a.name,
    async handle({ name }, ctx) {
        const mwn = await ctx.mwn();
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS /schema response shape; trusted at this boundary
        const data = (await swsRequest(mwn, {
            method: 'GET',
            path: `/schema/${encodeURIComponent(name)}`,
        }));
        if (data.schema === undefined || data.schema === null) {
            return ctx.format.notFound(`SWS schema "${name}" not found`);
        }
        const definitions = data.schema.propertyDefinitions ?? {};
        const properties = Object.entries(definitions).map(([propName, def]) => ({
            name: propName,
            ...def,
        }));
        return ctx.format.ok({
            name,
            ...(data.schema.formatVersion !== undefined
                ? { formatVersion: data.schema.formatVersion }
                : {}),
            ...(data.schema.id !== undefined ? { id: data.schema.id } : {}),
            ...(data.schema.identityLanguage !== undefined
                ? { identityLanguage: data.schema.identityLanguage }
                : {}),
            ...(data.schema.labels !== undefined ? { labels: data.schema.labels } : {}),
            ...(data.schema.descriptions !== undefined ? { descriptions: data.schema.descriptions } : {}),
            ...(data.schema.fieldKeyChanges !== undefined
                ? { fieldKeyChanges: data.schema.fieldKeyChanges }
                : {}),
            description: data.schema.description ?? '',
            ...(data.schema.subjectsAreDependent === true ? { subjectsAreDependent: true } : {}),
            properties,
        });
    },
};
