import { z } from 'zod';
var CategoryMemberType;
(function (CategoryMemberType) {
    CategoryMemberType["file"] = "file";
    CategoryMemberType["page"] = "page";
    CategoryMemberType["subcat"] = "subcat";
})(CategoryMemberType || (CategoryMemberType = {}));
export function normalizeCategoryTitle(input, namespaceNames = {}) {
    const title = input.trim().replace(/^:\s*/, '');
    const colon = title.indexOf(':');
    if (colon !== -1) {
        const prefix = title.slice(0, colon).trim().replace(/ /g, '_').toLowerCase();
        if (prefix === 'category' ||
            (Object.hasOwn(namespaceNames, prefix) && namespaceNames[prefix] === 14)) {
            return `Category:${title.slice(colon + 1).trim()}`;
        }
    }
    return `Category:${title}`;
}
const inputSchema = {
    category: z
        .string()
        .describe('Category name, with an optional canonical, localized or alias category-namespace prefix'),
    types: z
        .array(z.nativeEnum(CategoryMemberType))
        .optional()
        .describe('Types of members to include'),
    namespaces: z
        .array(z.number().int().nonnegative())
        .optional()
        .describe('Namespace IDs to filter by'),
    limit: z.number().int().min(1).max(500).optional().describe('Maximum members to return (1..500)'),
    continueFrom: z
        .string()
        .optional()
        .describe('Opaque continuation token from the previous response; omit on first call'),
};
export const getCategoryMembers = {
    name: 'get-category-members',
    description: "Lists members of a category, accepting canonical/localized category namespace prefixes and aliases from the targeted wiki, and returning each member's page ID, namespace ID, and wiki page title. Optionally filter by member type (page, file, subcat) or by namespace ID — filters apply server-side before the cap. Returns up to 500 members per call; paginate with continueFrom (opaque cursor echoed from the previous response). A member's type is omitted when it is an ordinary page (present only for files and subcategories).",
    inputSchema,
    annotations: {
        title: 'Get category members',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    failureVerb: 'retrieve category members',
    target: (a) => a.category,
    async handle({ category, types, namespaces, limit, continueFrom }, ctx) {
        const mwn = await ctx.mwn();
        let namespaceNames = {};
        const input = category.trim().replace(/^:\s*/, '');
        if (input.includes(':') && !/^category\s*:/i.test(input)) {
            // Read namespace data through the API: mwn's method-error proxy does not
            // preserve the static properties of its Title class.
            const data = await mwn.request({
                action: 'query',
                meta: 'siteinfo',
                siprop: 'namespaces|namespacealiases',
                formatversion: '2',
            });
            const query = data.query;
            if (!query || typeof query.namespaces !== 'object' || query.namespaces === null) {
                return ctx.format.error('upstream_failure', 'Cannot resolve the category namespace prefix. Retry or use the canonical Category: prefix.');
            }
            // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- MediaWiki siteinfo namespace boundary
            for (const ns of Object.values(query.namespaces)) {
                if (ns.id !== 14)
                    continue;
                for (const name of [ns.name, ns.canonical])
                    if (typeof name === 'string')
                        namespaceNames[name.toLowerCase().replace(/ /g, '_')] = 14;
            }
            // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- MediaWiki namespace alias boundary
            for (const ns of (query.namespacealiases ?? [])) {
                if (ns.id === 14 && typeof ns.alias === 'string')
                    namespaceNames[ns.alias.toLowerCase().replace(/ /g, '_')] = 14;
            }
        }
        const params = {
            action: 'query',
            list: 'categorymembers',
            cmtitle: normalizeCategoryTitle(category, namespaceNames),
            cmprop: 'ids|title|type',
            formatversion: '2',
        };
        if (types && types.length > 0) {
            params.cmtype = types.join('|');
        }
        if (namespaces && namespaces.length > 0) {
            params.cmnamespace = namespaces.join('|');
        }
        params.cmlimit = limit ?? 500;
        if (continueFrom) {
            params.cmcontinue = continueFrom;
        }
        const response = await mwn.request(params);
        const members = response.query?.categorymembers ?? [];
        const nextCursor = response.continue?.cmcontinue;
        const truncation = nextCursor
            ? {
                reason: 'more-available',
                returnedCount: members.length,
                itemNoun: 'members',
                toolName: 'get-category-members',
                continueWith: { param: 'continueFrom', value: nextCursor },
            }
            : null;
        return ctx.format.ok({
            members: members.map((m) => ({
                title: m.title,
                pageId: m.pageid,
                namespace: m.ns,
                ...(m.type !== undefined && m.type !== 'page' ? { type: m.type } : {}),
            })),
            ...(truncation !== null ? { truncation } : {}),
        });
    },
};
