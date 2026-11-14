import { usesOAuthSignIn } from '../runtime/authShape.ts';
import { getRuntimeToken, withRequestFields } from '../runtime/requestContext.ts';
import type { WikiProbe, WikiInspection } from '../wikis/wikiProbe.ts';
import type { WikiRegistry } from '../wikis/wikiRegistry.ts';
import type { StdioSignIn } from './stdioSignIn.ts';

/** Supplies the private wiki's own OAuth session before extension discovery. */
export class PrivateWikiProbe implements WikiProbe {
	public constructor(
		private readonly probe: WikiProbe,
		private readonly wikis: WikiRegistry,
		private readonly signIn: Pick<StdioSignIn, 'acquire'> | null,
	) {}

	public async inspect(wikiKey: string): Promise<WikiInspection> {
		const config = this.wikis.get(wikiKey);
		if (
			config?.private !== true ||
			!usesOAuthSignIn(config) ||
			this.signIn === null ||
			getRuntimeToken(wikiKey) !== undefined
		) {
			return this.probe.inspect(wikiKey);
		}
		try {
			const token = await this.signIn.acquire(wikiKey, {
				wiki: { server: config.server, scriptpath: config.scriptpath },
				oauth2ClientId: config.oauth2ClientId,
				callbackPort:
					typeof config.oauth2CallbackPort === 'number' ? config.oauth2CallbackPort : undefined,
			});
			return await withRequestFields({ runtimeToken: { value: token, wikiKey } }, () =>
				this.probe.inspect(wikiKey),
			);
		} catch {
			// WikiProbe is total: a failed sign-in must not reject discovery for
			// the other wikis or expose details of an authentication failure.
			return { reachable: false, extensions: new Set() };
		}
	}

	public invalidate(wikiKey: string): void {
		this.probe.invalidate(wikiKey);
	}
}
