import { tailoredError } from '../errors/tailoredErrors.js';
import { errorMessage } from '../errors/errorMessage.js';
import { getCallerId, getRequestSignal, withRequestFields } from './requestContext.js';
import { callDeadline, WIKI_CALL_TIMEOUT_MS } from './callDeadline.js';
import { WikiTimeoutError } from '../errors/wikiTimeoutError.js';
import { isWikiScoped, normalizeWikiArg } from './wikiArg.js';
import { emitToolCall, extractUpstreamStatus, parseEnvelope, } from './instrument.js';
import { structuredResult } from '../results/response.js';
import { checkWikiCapability, isWriteTool } from './wikiCapability.js';
import { monotonicNow } from './clock.js';
import { usesOAuthSignIn } from './authShape.js';
// The budget can expire before a write is submitted or after it is in flight,
// and the server cannot tell which. A caller that replays an append assuming it
// never landed would add the content twice.
const WRITE_TIMEOUT_CAVEAT = '. The change may or may not have been applied — check the wiki before retrying';
export function dispatch(tool, ctx) {
    return async (args) => {
        let resolved;
        if (isWikiScoped(tool)) {
            // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- the `wiki` field is merged into the schema by register(); not in the static TSchema
            const raw = args.wiki;
            // normalizeWikiArg trims and strips the mcp://wikis/ prefix, so a
            // whitespace-only or bare-prefix value normalizes to '' — treat that
            // as omitted and fall back to the default wiki.
            const normalized = typeof raw === 'string' ? normalizeWikiArg(raw) : '';
            const key = normalized !== '' ? normalized : ctx.defaultWikiKey;
            const config = ctx.wikis.get(key);
            if (config === undefined) {
                const configured = Object.keys(ctx.wikis.getAll());
                return ctx.format.invalidInput(`Wiki "${key}" not found. Configured wikis: ${configured.join(', ')}`);
            }
            resolved = { key, config };
            const guardError = await checkWikiCapability(tool.name, resolved, ctx);
            if (guardError) {
                return guardError;
            }
        }
        const wiki = resolved;
        const callCtx = contextFor(ctx, wiki);
        const body = async () => {
            // Only a wiki-scoped tool acts on the wiki it resolves, so only it needs
            // that wiki's token. The rest work on server-local state or anonymous
            // probes, and must stay reachable while that wiki's sign-in is broken.
            const { stdioSignIn } = ctx.auth;
            if (stdioSignIn !== null && wiki !== undefined && usesOAuthSignIn(wiki.config)) {
                const { key, config } = wiki;
                let token;
                try {
                    token = await stdioSignIn.acquire(key, {
                        wiki: { server: config.server, scriptpath: config.scriptpath },
                        oauth2ClientId: config.oauth2ClientId,
                        callbackPort: typeof config.oauth2CallbackPort === 'number' ? config.oauth2CallbackPort : undefined,
                    });
                }
                catch (err) {
                    const message = errorMessage(err);
                    return ctx.format.error('authentication', `OAuth login required: ${message}`);
                }
                // withRequestFields (not withRequestContext) so the request's signal
                // survives into the token-scoped run.
                return withRequestFields({ runtimeToken: { value: token, wikiKey: key } }, () => runDispatchInner(tool, callCtx, args, key));
            }
            return runDispatchInner(tool, callCtx, args, wiki?.key);
        };
        return body();
    };
}
function contextFor(ctx, wiki) {
    if (wiki === undefined) {
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- a tool that acts on no wiki takes the shared context as it is
        return ctx;
    }
    const bound = {
        wiki,
        mwn: () => ctx.mwnFor(wiki.key),
        edit: ctx.editFor(wiki),
    };
    // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- a tool that acts on a wiki takes the shared context and that wiki's services
    return { ...ctx, ...bound };
}
async function runDispatchInner(tool, ctx, args, resolvedKey) {
    const started = monotonicNow();
    let outcome = 'success';
    let errorText;
    let upstreamStatus;
    let result;
    let agentSnapshot;
    let agentContextUnavailable = false;
    let uncertainGraphConfiguration = false;
    try {
        // Armed here rather than around the whole of dispatch() so an interactive
        // OAuth login does not spend the budget before the tool runs.
        result = await withRequestFields({ deadline: callDeadline(WIKI_CALL_TIMEOUT_MS, 'calling') }, async () => {
            if (resolvedKey !== undefined && ctx.agentConnection && tool.name !== 'get-agent-context') {
                try {
                    // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- resolvedKey is present only for a wiki-bound ToolContext
                    agentSnapshot = await ctx.agentConnection.get(ctx);
                }
                catch {
                    // Context failure must not turn an otherwise usable read into a login failure.
                    agentContextUnavailable = true;
                    ctx.logger.notice('Agent context unavailable', { wiki: resolvedKey });
                }
            }
            if (resolvedKey !== undefined &&
                ctx.queryCapabilities &&
                ['sws-cypher-query', 'sws-sparql-query'].includes(tool.name)) {
                // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- resolved wiki implies ToolContext
                const capabilities = await ctx.queryCapabilities.inspect(ctx);
                const backend = tool.name === 'sws-cypher-query' ? 'cypher' : 'sparql';
                uncertainGraphConfiguration = capabilities[backend] === 'unknown';
                if (capabilities[backend] === 'not-configured')
                    return ctx.format.invalidInput(`SWS ${backend} backend is not configured on this wiki. Use stored entity fields or SMW instead.`);
            }
            return tool.handle(args, ctx);
        });
        if (result.isError) {
            const first = result.content[0];
            const text = first !== undefined && 'text' in first && typeof first.text === 'string'
                ? first.text
                : undefined;
            const env = parseEnvelope(text);
            // Fall back to upstream_failure when the envelope is missing or
            // unparseable rather than letting outcome stay 'success' — that
            // would emit a misleading info-level telemetry line on a result
            // that's flagged as an error.
            outcome = env.category ?? 'upstream_failure';
            if (env.message) {
                errorText = env.message;
            }
        }
    }
    catch (err) {
        const classified = ctx.errors.classify(err);
        const rawMessage = errorMessage(err);
        const tailored = tailoredError(tool.tailoredErrors, classified.code, rawMessage);
        const category = tailored?.category ?? classified.category;
        outcome = category;
        upstreamStatus = extractUpstreamStatus(err);
        const verb = tool.failureVerb ?? tool.name;
        let finalMessage = tailored?.message ?? `Failed to ${verb}: ${rawMessage}`;
        if (err instanceof WikiTimeoutError && err.phase === 'calling' && isWriteTool(tool)) {
            finalMessage += WRITE_TIMEOUT_CAVEAT;
        }
        errorText = finalMessage;
        // A cancelled call fails on the way down — mwn surfaces the torn-down
        // request as a malformed response rather than as an abort — so the
        // classification describes the teardown, not the wiki. Reporting it as an
        // upstream failure would make every cancellation look like an outage.
        // The result is still built, and still discarded: the caller has gone.
        if (getRequestSignal()?.aborted === true) {
            ctx.logger.info('Tool call cancelled', { tool: tool.name });
        }
        else {
            ctx.logger.error('Tool failed', {
                tool: tool.name,
                category,
                code: classified.code,
            });
        }
        result = ctx.format.error(category, finalMessage, classified.code);
    }
    // Covers both failure routes above: a throw, and a tool that catches the
    // abort itself and returns an error result.
    if (outcome !== 'success' && getRequestSignal()?.aborted === true) {
        outcome = 'cancelled';
    }
    // Echo the resolved wiki back to the caller. Re-wrap via structuredResult
    // rather than mutating structuredContent so the rendered content[0].text
    // stays in sync — a plain mutation would only touch the structured channel.
    // Assumes no wiki-scoped tool emits its own top-level `wiki` field; the
    // spread places the resolved key last, so it would silently override one.
    if (resolvedKey !== undefined &&
        !result.isError &&
        typeof result.structuredContent === 'object' &&
        result.structuredContent !== null &&
        !Array.isArray(result.structuredContent)) {
        result = structuredResult({ ...result.structuredContent, wiki: resolvedKey });
    }
    if (agentSnapshot && ctx.agentConnection) {
        result = {
            ...result,
            content: [
                ...result.content,
                { type: 'text', text: ctx.agentConnection.notice(agentSnapshot) },
            ],
        };
    }
    if (agentContextUnavailable) {
        result = {
            ...result,
            content: [
                ...result.content,
                {
                    type: 'text',
                    text: 'Agent context unavailable: identity/profile could not be loaded. get-agent-context provides a separate diagnostic; no cached identity is assumed.',
                },
            ],
        };
    }
    if (uncertainGraphConfiguration) {
        result = {
            ...result,
            content: [
                ...result.content,
                {
                    type: 'text',
                    text: JSON.stringify({
                        queryAdvisory: 'Backend configuration could not be established. The requested read-only query was attempted normally; its result reports actual availability. Configuration uncertainty is not evidence of stale or missing data.',
                    }),
                },
            ],
        };
    }
    emitToolCall(ctx.metrics, {
        toolName: tool.name,
        target: tool.target,
        args,
        started,
        result,
        outcome,
        upstreamStatus,
        errorMessage: errorText,
        caller: getCallerId(),
        wikiKey: resolvedKey ?? ctx.defaultWikiKey,
    });
    return result;
}
