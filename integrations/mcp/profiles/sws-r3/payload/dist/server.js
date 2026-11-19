import { QueryCapabilities } from './agent/queryCapabilities.js';
import { AgentConnection } from './agent/connection.js';
import { McpServer } from '@modelcontextprotocol/server';
import { createRequire } from 'node:module';
import { allTools, registerAllTools } from './tools/index.js';
import { registerAllResources } from './resources/index.js';
import { reconcileTools } from './runtime/reconcile.js';
import { extensionPacks } from './tools/extensions/index.js';
// https://github.com/nodejs/node/issues/51347#issuecomment-2111337854
// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- compile-time JSON import; ESM `import ... assert { type: 'json' }` migration is a separate follow-up
const serverInfo = createRequire(import.meta.url)('../server.json');
const SERVER_NAME = 'mediawiki-mcp-server';
const SERVER_INSTRUCTIONS = `Tools and resources for working with one or more MediaWiki wikis. Each configured wiki appears as an \`mcp://wikis/{wikiKey}\` resource. Every tool that operates on a wiki accepts an optional \`wiki\` argument naming the wiki to act on (the wiki-management and OAuth tools do not) — pass a wiki key (or its \`mcp://wikis/{wikiKey}\` URI). Omit it to use the configured default wiki. There is no stateful "current wiki": each call targets exactly the wiki it names, and every response reports the wiki it ran against. Use get-agent-context when starting wiki work; get-skill-topic returns bundled SWS help without login. Choose full-text search, categories and structured SWS queries according to the question and combine them when useful; no source order is mandatory. Inspect schema field definitions before building field-specific filters, not before every simple search. Application/use-case descriptions can help select candidates if available, whatever the fields are called; no particular field name or search checkbox is required. Page titles alone need not describe purpose. Check graph capabilities as guidance: known absence prevents use, unknown status does not block a requested read-only query. Indexes can lag behind saves; no matches is not proof of absence. Read candidate documentation on demand and use external research when useful. Existing tools remain available. The first authenticated wiki operation loads the account profile and creates User:<username>/profil if it is missing and permitted. Existing profiles are preserved. Local stdio caches it for this connection; stateless HTTP reloads per request. Call \`list-wikis\` (when offered for multiple wikis) to discover the configured wikis, their keys, and which extension tools each one supports.

Writes, deletes, and uploads act as whichever identity the deployment provides: the signed-in user when hosted OAuth sign-in is configured, otherwise the credentials configured on the targeted wiki. Do not send an \`Authorization\` header of your own unless the deployment asked you to; one is refused rather than used.

Tool errors fall into seven categories: \`not_found\`, \`permission_denied\`, \`invalid_input\`, \`conflict\`, \`authentication\`, \`rate_limited\`, and \`upstream_failure\`. Reads that exceed a per-call cap return a truncation marker describing what was returned and how to fetch the rest.`;
// Everything cacheable here changes only when a reconcile pass runs (a wiki is
// added or removed, or an extension probe flips), and subscribed clients learn
// of that through listChanged events — the TTL only bounds staleness for
// clients that poll instead. Private scope: tool and resource lists reflect
// this deployment's wiki configuration, so a shared cache must not serve one
// deployment's lists to another behind the same intermediary.
const CACHE_HINT = { ttlMs: 60_000, cacheScope: 'private' };
export const createServer = async (baseCtx, options = {}) => {
    const queryCapabilities = new QueryCapabilities(async () => applyGates());
    const ctx = {
        ...baseCtx,
        agentConnection: new AgentConnection(),
        queryCapabilities,
        wikiProbe: {
            inspect: async (key) => ({
                ...(await baseCtx.wikiProbe.inspect(key)),
                swsQueryBackends: queryCapabilities.peek(key),
            }),
            invalidate: (key) => baseCtx.wikiProbe.invalidate(key),
        },
    };
    const server = new McpServer({
        name: SERVER_NAME,
        title: serverInfo.title,
        version: serverInfo.version,
        description: serverInfo.description,
    }, {
        capabilities: {
            resources: {
                listChanged: true,
            },
            tools: {
                listChanged: true,
            },
        },
        instructions: SERVER_INSTRUCTIONS,
        cacheHints: {
            'tools/list': CACHE_HINT,
            'resources/list': CACHE_HINT,
            'resources/templates/list': CACHE_HINT,
            'resources/read': CACHE_HINT,
            'server/discover': CACHE_HINT,
        },
    });
    const publisher = options.publisher ?? {
        // A live connection's RegisteredTool toggles already emit their own
        // listChanged; only the resource list needs an explicit push.
        toolsChanged: () => { },
        resourcesChanged: () => {
            server.sendResourceListChanged();
        },
    };
    const tools = new Map();
    const applyGates = () => reconcileTools(tools, {
        wikiRegistry: ctx.wikis,
        transport: ctx.auth.transport,
        wikiProbe: ctx.wikiProbe,
        extensionPacks,
        descriptors: allTools,
    });
    // The reconcile callback add-wiki / remove-wiki invoke: re-gate, then tell
    // clients the wiki resource list (and with it the tool list) may have
    // changed.
    const reconcile = async () => {
        await applyGates();
        publisher.resourcesChanged();
        publisher.toolsChanged();
    };
    const registered = registerAllTools(server, reconcile, ctx);
    for (const [name, tool] of registered) {
        tools.set(name, tool);
    }
    registerAllResources(server, ctx);
    // Construction gates without publishing: a fresh instance has no
    // subscribers yet, and under a per-request factory a construction-time
    // publish would fan change events out to unrelated clients on every
    // request.
    await applyGates();
    return server;
};
