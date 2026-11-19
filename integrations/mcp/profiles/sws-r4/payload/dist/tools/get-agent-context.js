import { checkWikiCapability } from '../runtime/wikiCapability.js';
import { isWikiScoped } from '../runtime/wikiArg.js';
import { AgentConnection } from '../agent/connection.js';
import { HELP_TOPICS } from '../agent/help.js';
import { standardTools } from './standardTools.js';
export const getAgentContext = {
    name: 'get-agent-context',
    description: 'Returns the authenticated MediaWiki identity, account profile, source priorities, bundled help topics and detected tool capabilities. Required when starting work with a wiki. Creates User:<authenticated username>/profil if missing and permitted; existing profiles are never overwritten. The profile is loaded once per stdio connection or per stateless HTTP request.',
    inputSchema: {},
    annotations: {
        title: 'Get agent context',
        readOnlyHint: true,
        destructiveHint: false,
        idempotentHint: true,
        openWorldHint: true,
    },
    async handle(_args, ctx) {
        const connection = ctx.agentConnection ?? new AgentConnection();
        const snapshot = await connection.get(ctx);
        const inspection = await ctx.wikiProbe.inspect(ctx.wiki.key);
        const allWikis = ctx.wikis.getAll();
        const offeredContext = {
            allWikis,
            wikiCount: Object.keys(allWikis).length,
            allowManagement: ctx.wikis.isManagementAllowed(),
            transport: ctx.auth.transport,
            wikiProbe: ctx.wikiProbe,
        };
        const tools = await Promise.all(standardTools.map(async (tool) => ({
            name: tool.name,
            available: (tool.offeredWhen?.(offeredContext) ?? true) &&
                (!isWikiScoped(tool) ||
                    (await checkWikiCapability(tool.name, ctx.wiki, ctx)) === undefined),
        })));
        let extensions;
        try {
            const data = (await (await ctx.mwn()).request({
                action: 'query',
                meta: 'siteinfo',
                siprop: 'extensions',
                formatversion: '2',
            }));
            extensions = data.query?.extensions?.map(({ name, version }) => ({ name, version }));
        }
        catch {
            /* Capability hints still work when version reporting is restricted. */
        }
        const swsVersion = extensions?.find((extension) => extension.name === 'SemanticWikiSchema')?.version;
        return ctx.format.ok({
            ...snapshot,
            availableTools: tools.filter((tool) => tool.available).map((tool) => tool.name),
            conditionalTools: tools
                .filter((tool) => ['sws-cypher-query', 'sws-sparql-query'].includes(tool.name) &&
                snapshot.queryCapabilities?.[tool.name === 'sws-cypher-query' ? 'cypher' : 'sparql'] !==
                    'configured')
                .map((tool) => ({
                name: tool.name,
                callAllowed: tool.available,
                configuration: snapshot.queryCapabilities?.[tool.name === 'sws-cypher-query' ? 'cypher' : 'sparql'] ??
                    'unknown',
            })),
            toolListScope: 'Target wiki capability/configuration gates; wiki rights, OAuth grants, and page protection are checked by the actual request. Management tools are separate.',
            helpTopics: HELP_TOPICS,
            capabilities: {
                detectedExtensions: [...inspection.extensions],
                extensionVersions: extensions ?? null,
                helpCompatibility: ['0.6.0', '0.6.0-beta.2', '0.6.0-beta', '0.6.0-beta.1'].includes(swsVersion ?? '')
                    ? 'verified-source-contract'
                    : 'version-unverified; endpoints require runtime checks',
                backendStatus: 'Configuration is reported by queryCapabilities. Connectivity, token grants and data freshness remain unverified.',
            },
        });
    },
};
