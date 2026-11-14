#!/usr/bin/env node
import { serveStdio } from '@modelcontextprotocol/server/stdio';
import { logger } from '../runtime/logger.js';
import { createServer } from '../server.js';
import { emitStartupBanner } from '../runtime/banner.js';
import { createSharedContext } from '../runtime/createContext.js';
import { registerShutdownHandlers } from '../runtime/shutdown.js';
import { loadConfigFromFile } from '../config/loadConfig.js';
import { createAppState } from '../wikis/state.js';
import { readAuthPolicy } from '../runtime/authShape.js';
import { PrivateWikiProbe } from '../auth/privateWikiProbe.js';
import { StdioSignIn } from '../auth/stdioSignIn.js';
import { readMetrics } from '../runtime/metrics.js';
async function main() {
    const config = loadConfigFromFile();
    const baseState = createAppState(config);
    const auth = readAuthPolicy(process.env, 'stdio', null, new StdioSignIn());
    const state = {
        ...baseState,
        wikiProbe: new PrivateWikiProbe(baseState.wikiProbe, baseState.wikiRegistry, auth.stdioSignIn),
    };
    emitStartupBanner({ transport: 'stdio' }, {
        wikiRegistry: state.wikiRegistry,
        defaultWikiKey: state.defaultWikiKey,
        uploadDirs: state.uploadDirs,
        auth,
    });
    const ctx = createSharedContext({
        logger,
        state,
        auth,
        metrics: readMetrics(process.env, 'stdio'),
    });
    // serveStdio owns the connection: the opening exchange pins the era, one
    // instance from the factory serves the connection's lifetime, and on a
    // modern-pinned connection the entry rewrites outbound change
    // notifications onto its subscriptions/listen streams — so the default
    // change publisher in createServer covers both eras here.
    const handle = serveStdio(() => createServer(ctx), {
        onerror: (error) => logger.error(`stdio serving error: ${error.message}`),
    });
    // Stdio has no in-flight queue, so grace doesn't apply — log graceMs: 0
    // to make that explicit in the shutdown event.
    registerShutdownHandlers({
        transport: 'stdio',
        graceMs: 0,
        stdioTransport: handle,
    });
}
main().catch((error) => {
    // Bootstrap fail-safe: see the equivalent block in src/index.ts. Logger
    // module not used here intentionally so a logger import failure can't
    // suppress this path. Exit cleanly instead of re-throwing — `throw` from
    // inside .catch creates a detached promise chain that fires as an
    // unhandled-rejection warning on top of our own message.
    console.error('Server error:', error);
    process.exit(1);
});
