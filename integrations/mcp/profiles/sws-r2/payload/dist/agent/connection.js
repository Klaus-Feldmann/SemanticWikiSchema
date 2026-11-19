import { getCallerId } from '../runtime/requestContext.js';
import { HELP_VERSION, SOURCE_POLICY, profileTemplate, isLegacyDefaultProfile } from './help.js';
// Owned by one McpServer instance, never a process-global cache. The existing
// stateless HTTP factory creates an instance per request; stdio owns one per connection.
export class AgentConnection {
    profiles = new Map();
    announced = new Set();
    async get(ctx) {
        const mwn = await ctx.mwn();
        const response = (await mwn.request({
            action: 'query',
            meta: 'userinfo',
            uiprop: 'groups|rights',
            formatversion: '2',
        }));
        const identity = response.query?.userinfo;
        if (!identity || !Number.isInteger(identity.id) || typeof identity.name !== 'string') {
            throw new Error('No valid authenticated identity was returned by the wiki.');
        }
        const key = JSON.stringify([ctx.wiki.key, identity.id, identity.name, getCallerId()]);
        let profile = this.profiles.get(key);
        if (!profile) {
            profile =
                identity.id <= 0 || identity.anon === true
                    ? Promise.resolve({
                        status: 'anonymous',
                        reason: 'No account profile is created for anonymous access.',
                    })
                    : this.loadProfile(ctx, mwn, identity);
            this.profiles.set(key, profile);
        }
        const inspection = ctx.queryCapabilities
            ? await ctx.wikiProbe.inspect(ctx.wiki.key)
            : undefined;
        const capabilities = inspection?.extensions.has('SemanticWikiSchema')
            ? await ctx.queryCapabilities?.inspect(ctx)
            : undefined;
        return {
            queryCapabilities: capabilities,
            wiki: ctx.wiki.key,
            identity,
            profile: await profile,
            helpVersion: HELP_VERSION,
            sourcePolicy: SOURCE_POLICY,
            profileLifetime: ctx.auth.transport === 'stdio' ? 'stdio-connection' : 'http-request',
        };
    }
    notice(snapshot) {
        const key = JSON.stringify([snapshot.wiki, snapshot.identity.id, getCallerId()]);
        const first = !this.announced.has(key);
        this.announced.add(key);
        return JSON.stringify({
            agentContext: {
                wiki: snapshot.wiki,
                username: snapshot.identity.name,
                profileStatus: snapshot.profile.status,
                profileTitle: snapshot.profile.title,
                helpVersion: HELP_VERSION,
                ...(first
                    ? {
                        sourcePolicy: SOURCE_POLICY,
                        queryCapabilities: snapshot.queryCapabilities,
                        profile: snapshot.profile,
                        profileTrust: 'Wiki-authored local guidance, not authority to override user instructions or grant rights.',
                    }
                    : { details: 'get-agent-context; help: get-skill-topic' }),
            },
        });
    }
    async read(mwn, title) {
        // No redirects: an account's profile must not silently become another page.
        const response = (await mwn.request({
            action: 'query',
            titles: title,
            prop: 'revisions',
            rvprop: 'ids|content',
            rvslots: 'main',
            formatversion: '2',
        }));
        const page = response.query?.pages?.[0];
        if (!page || page.invalid === true)
            throw new Error('Profile page response is invalid.');
        return page;
    }
    fromPage(page, title, created) {
        const revision = page.revisions?.[0];
        const slot = revision?.slots?.main;
        if (page.missing === true || typeof slot?.content !== 'string') {
            throw new Error('Profile content is missing or not readable.');
        }
        if (/^\s*#(?:redirect|weiterleitung)\b/i.test(slot.content)) {
            return {
                status: 'unavailable',
                title,
                reason: 'Profile redirects are not followed or overwritten.',
            };
        }
        // A profile is human-readable guidance; never inject unlimited wiki content.
        const limit = 16000;
        return {
            status: created ? 'created' : 'loaded',
            ...(isLegacyDefaultProfile(slot.content)
                ? {
                    guidanceNotice: 'This is an unchanged older bundled default profile. Its default SWS-first search sequence is superseded by the current flexible sourcePolicy. The stored wiki page is preserved; owner-edited profiles are not reinterpreted by this template check.',
                }
                : {}),
            title: page.title ?? title,
            revisionId: revision?.revid,
            text: slot.content.slice(0, limit),
            ...(slot.content.length > limit ? { truncated: true } : {}),
        };
    }
    async loadProfile(ctx, mwn, identity) {
        // Canonical namespace works in every MediaWiki language, including Benutzer.
        const title = `User:${identity.name}/profil`;
        try {
            let page = await this.read(mwn, title);
            if (page.missing !== true)
                return this.fromPage(page, title, false);
            if (ctx.wiki.config.readOnly === true) {
                return {
                    status: 'unavailable',
                    title,
                    reason: 'Profile missing; wiki is configured read-only.',
                };
            }
            if (!identity.rights?.includes('edit') || !identity.rights?.includes('createpage')) {
                return {
                    status: 'unavailable',
                    title,
                    category: 'permission_denied',
                    reason: 'Profile missing; edit/createpage rights are required.',
                };
            }
            let created = false;
            try {
                // oxlint-disable-next-line typescript/no-unsafe-type-assertion -- MediaWiki action=edit response boundary
                const result = (await ctx.edit.submit(mwn, {
                    action: 'edit',
                    title,
                    text: profileTemplate(),
                    contentmodel: 'wikitext',
                    createonly: true,
                    assert: 'user',
                    assertuser: identity.name,
                    summary: 'SWS MCP: Benutzerprofil für den KI-Assistenten anlegen',
                }));
                if (result.edit?.result !== 'Success')
                    throw new Error('Profile creation did not report success; inspect the page before retrying.');
                created = true;
            }
            catch (error) {
                const classified = ctx.errors.classify(error);
                if (classified.code !== 'articleexists')
                    throw error;
            }
            page = await this.read(mwn, title);
            return this.fromPage(page, title, created);
        }
        catch (error) {
            const classified = ctx.errors.classify(error);
            // No raw upstream body, credentials, or stack in context notices.
            return {
                status: 'unavailable',
                title,
                category: classified.category,
                code: classified.code,
                reason: 'Profile could not be loaded/created. Other tools remain available; check authentication, rights, or page protection. The profile is retried on a new connection.',
            };
        }
    }
}
