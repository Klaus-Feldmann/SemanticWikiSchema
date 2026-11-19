import { isWikiScoped } from './wikiArg.js';
import { buildToolInputSchema } from './wikiArg.js';
import { withRequestFields } from './requestContext.js';
/**
 * Runs `fn` with the SDK request's cancellation signal in the request scope, so
 * `ctx.mwn()` can apply it to that request's MediaWiki calls. The signal lives
 * at `ctx.mcpReq.signal`, not on the context root. The key is written only when
 * a signal is actually present, so a scope set further out is never clobbered
 * with `undefined`.
 */
function withRequestScope(ctx, fn) {
    const signal = ctx?.mcpReq?.signal;
    return signal === undefined ? fn() : withRequestFields({ signal }, fn);
}
export function register(server, tool, handler) {
    return server.registerTool(tool.name, {
        description: tool.description +
            (isWikiScoped(tool)
                ? ' Connection bootstrap reads the account profile and can create it if missing; existing profiles are preserved.'
                : ''),
        // `buildToolInputSchema` wraps the descriptor's own shape (optionally
        // with the shared `wiki` field merged in) in a `z.object()`. The cast
        // re-narrows it to `TSchema` for the SDK's generic boundary; the merged
        // `wiki` field is an optional extra the handler simply ignores.
        // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- generic boundary; the merged schema is a superset of TSchema
        inputSchema: buildToolInputSchema(tool),
        annotations: isWikiScoped(tool)
            ? { ...tool.annotations, readOnlyHint: false }
            : tool.annotations,
    }, 
    // The SDK callback signature is `(args, ctx) => ...`. Descriptor handlers
    // take only `args`, so the `ctx` the SDK supplies is consumed here: its
    // cancellation signal goes into the request scope, where `ctx.mwn()`
    // picks it up and applies it to that request's MediaWiki calls. The
    // parameter is typed as the SDK's own `ServerContext` rather than a
    // hand-written shape so the compiler checks the property path — the cast
    // below erases it, and an invented shape silently reads `undefined`.
    // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- generic boundary; MCP SDK's ToolCallback can't be unified with our typed handler
    ((args, ctx) => withRequestScope(ctx, () => handler(args))));
}
