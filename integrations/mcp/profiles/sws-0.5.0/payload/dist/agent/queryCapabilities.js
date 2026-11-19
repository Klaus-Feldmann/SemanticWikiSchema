import { withCallBounds } from '../wikis/abortableMwn.js';
import { callDeadline } from '../runtime/callDeadline.js';
import { getRequestSignal } from '../runtime/requestContext.js';
import { getCallerId } from '../runtime/requestContext.js';
import { monotonicNow } from '../runtime/clock.js';
const UNKNOWN = { cypher: 'unknown', sparql: 'unknown' };
const BACKENDS = ['cypher', 'sparql'];
const KEYS = BACKENDS.flatMap((backend) => [
    `sws-${backend}-raw-error-not-configured`,
    `sws-${backend}-error-empty-query`,
    `sws-${backend}-error-permission-denied`,
]);
const messageEnvelope = (value) => !!value &&
    typeof value === 'object' &&
    'query' in value &&
    !!value.query &&
    typeof value.query === 'object' &&
    'allmessages' in value.query &&
    Array.isArray(value.query.allmessages);
function plain(html) {
    const entities = {
        amp: '&',
        lt: '<',
        gt: '>',
        quot: '"',
        apos: "'",
        nbsp: ' ',
    };
    return html
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/<[^>]*>/g, '')
        .replace(/&(#x[\da-f]+|#\d+|amp|lt|gt|quot|apos|nbsp);/gi, (_m, name) => {
        if (name.startsWith('#')) {
            const cp = name[1].toLowerCase() === 'x' ? parseInt(name.slice(2), 16) : parseInt(name.slice(1), 10);
            return cp >= 0 && cp <= 0x10ffff ? String.fromCodePoint(cp) : '';
        }
        return entities[name.toLowerCase()] ?? '';
    })
        .replace(/\s+/g, ' ')
        .trim();
}
// Configuration discovery only: empty parser queries are rejected by SWS before
// any store access. Neither REST queries, page writes nor graph rebuilds occur.
export function backendStates(html, messages) {
    const result = { ...UNKNOWN };
    for (const backend of BACKENDS) {
        const match = html.match(new RegExp(`<div\\b[^>]*\\bid=["']sws-mcp-${backend}["'][^>]*>([\\s\\S]*?)</div>`, 'i'));
        if (!match || !/class=["'][^"']*\berror\b/.test(match[1]))
            continue;
        const rendered = plain(match[1]);
        const absentKey = `sws-${backend}-raw-error-not-configured`;
        const presentKeys = [
            `sws-${backend}-error-empty-query`,
            `sws-${backend}-error-permission-denied`,
        ];
        const matches = (key) => rendered === `(${key})` ||
            (typeof messages[key] === 'string' &&
                messages[key].trim() !== '' &&
                rendered === plain(messages[key]));
        const absent = matches(absentKey);
        const present = presentKeys.some(matches);
        // A locally overridden message collision cannot establish a configuration.
        if (absent !== present)
            result[backend] = absent ? 'not-configured' : 'configured';
    }
    return result;
}
export class QueryCapabilities {
    changed;
    now;
    cache = new Map();
    pending = new Map();
    visibility = new Map();
    constructor(changed = async () => { }, now = monotonicNow) {
        this.changed = changed;
        this.now = now;
    }
    peek(wikiKey) {
        const entry = this.visibility.get(wikiKey);
        return entry && entry.expires > this.now() ? entry.value : { ...UNKNOWN };
    }
    async inspect(ctx, refresh = false) {
        const key = JSON.stringify([ctx.wiki.key, getCallerId()]);
        const cached = this.cache.get(key);
        if (!refresh && cached && cached.expires > this.now())
            return cached.value;
        const existing = this.pending.get(key);
        if (existing)
            return existing;
        const pending = this.probe(ctx)
            .then(async (value) => {
            const ttl = value.cypher === 'unknown' || value.sparql === 'unknown' ? 5000 : 60000;
            this.cache.set(key, { value, expires: this.now() + ttl });
            const before = this.peek(ctx.wiki.key);
            this.visibility.set(ctx.wiki.key, {
                value: { cypher: value.cypher, sparql: value.sparql },
                expires: this.now() + ttl,
            });
            if (before.cypher !== value.cypher || before.sparql !== value.sparql)
                await this.changed();
            return value;
        })
            .finally(() => this.pending.delete(key));
        this.pending.set(key, pending);
        return pending;
    }
    async probe(ctx) {
        let states = { ...UNKNOWN };
        try {
            const inspection = await ctx.wikiProbe.inspect(ctx.wiki.key);
            const language = inspection.lang ?? 'en';
            const mwn = withCallBounds(await ctx.mwn(), callDeadline(5000, 'calling'), getRequestSignal());
            const messages = await mwn.request({
                action: 'query',
                meta: 'allmessages',
                ammessages: KEYS.join('|'),
                amlang: language,
                formatversion: '2',
            });
            const localized = messageEnvelope(messages)
                ? Object.fromEntries(messages.query.allmessages
                    .filter((m) => typeof m.name === 'string' && typeof m.content === 'string')
                    .map((m) => [m.name, m.content]))
                : {};
            const response = await mwn.request({
                action: 'parse',
                text: '<div id="sws-mcp-cypher">{{#cypher_raw:}}</div>\n<div id="sws-mcp-sparql">{{#sparql_raw:}}</div>',
                contentmodel: 'wikitext',
                prop: 'text',
                uselang: language,
                formatversion: '2',
            });
            // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- MediaWiki action=parse boundary, text checked below
            const parsed = response;
            if (typeof parsed?.parse?.text === 'string' && parsed.parse.text.length <= 75000)
                states = backendStates(parsed.parse.text, localized);
        }
        catch {
            /* A failed request means unknown, never absent; no upstream secrets in context. */
        }
        const recognized = states.cypher !== 'unknown' || states.sparql !== 'unknown';
        return {
            ...states,
            checkedAt: new Date().toISOString(),
            evidence: recognized ? 'empty-parser-query' : 'unrecognized-or-unavailable',
            connectivity: 'not-tested',
            dataFreshness: 'unknown',
            note: 'Configured does not prove connectivity, token permissions or data freshness. Unknown is retried after 5 seconds or explicit refresh. No store queries were executed.',
        };
    }
}
