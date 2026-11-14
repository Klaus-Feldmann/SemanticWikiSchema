import { usesOAuthSignIn } from '../runtime/authShape.js';
import { getRuntimeToken, withRequestFields } from '../runtime/requestContext.js';
/** Supplies the private wiki's own OAuth session before extension discovery. */
export class PrivateWikiProbe {
    probe;
    wikis;
    signIn;
    constructor(probe, wikis, signIn) {
        this.probe = probe;
        this.wikis = wikis;
        this.signIn = signIn;
    }
    async inspect(wikiKey) {
        const config = this.wikis.get(wikiKey);
        if (config?.private !== true ||
            !usesOAuthSignIn(config) ||
            this.signIn === null ||
            getRuntimeToken(wikiKey) !== undefined) {
            return this.probe.inspect(wikiKey);
        }
        try {
            const token = await this.signIn.acquire(wikiKey, {
                wiki: { server: config.server, scriptpath: config.scriptpath },
                oauth2ClientId: config.oauth2ClientId,
                callbackPort: typeof config.oauth2CallbackPort === 'number' ? config.oauth2CallbackPort : undefined,
            });
            return await withRequestFields({ runtimeToken: { value: token, wikiKey } }, () => this.probe.inspect(wikiKey));
        }
        catch {
            // WikiProbe is total: a failed sign-in must not reject discovery for
            // the other wikis or expose details of an authentication failure.
            return { reachable: false, extensions: new Set() };
        }
    }
    invalidate(wikiKey) {
        this.probe.invalidate(wikiKey);
    }
}
