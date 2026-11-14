import { describe, it, expect, vi } from 'vitest';
import { PrivateWikiProbe } from '../../src/auth/privateWikiProbe.ts';
import { getRuntimeToken, withRequestFields } from '../../src/runtime/requestContext.ts';
import { WikiRegistryImpl } from '../../src/wikis/wikiRegistry.ts';

const config = {
	sitename: 'Test',
	server: 'https://test.wiki',
	scriptpath: '/w',
	articlepath: '/wiki',
	private: true,
	oauth2ClientId: 'client',
};
function setup(extra = {}) {
	const wikis = new WikiRegistryImpl({ test: { ...config, ...extra } }, false);
	const inspect = vi.fn(async (key: string) => ({
		reachable: true,
		extensions: new Set([getRuntimeToken(key) ?? 'anonymous']),
	}));
	const invalidate = vi.fn();
	const acquire = vi.fn().mockResolvedValue('test-token');
	return {
		inspect,
		invalidate,
		acquire,
		probe: new PrivateWikiProbe({ inspect, invalidate }, wikis, { acquire }),
	};
}
describe('private wiki OAuth discovery', () => {
	it('binds discovery to its own wiki and restores the surrounding context', async () => {
		const { probe, acquire } = setup();
		const r = await probe.inspect('test');
		expect(r.extensions.has('test-token')).toBe(true);
		expect(acquire).toHaveBeenCalledWith(
			'test',
			expect.objectContaining({
				oauth2ClientId: 'client',
				wiki: { server: 'https://test.wiki', scriptpath: '/w' },
			}),
		);
		expect(getRuntimeToken('test')).toBeUndefined();
	});
	it('does not sign in to public wikis', async () => {
		const { probe, acquire } = setup({ private: false });
		await probe.inspect('test');
		expect(acquire).not.toHaveBeenCalled();
	});
	it('leaves non-OAuth credentials to the underlying probe', async () => {
		const { probe, acquire } = setup({ oauth2ClientId: undefined });
		await probe.inspect('test');
		expect(acquire).not.toHaveBeenCalled();
	});
	it('reuses the current wiki token', async () => {
		const { probe, acquire } = setup();
		const r = await withRequestFields({ runtimeToken: { value: 'current', wikiKey: 'test' } }, () =>
			probe.inspect('test'),
		);
		expect(r.extensions.has('current')).toBe(true);
		expect(acquire).not.toHaveBeenCalled();
	});
	it('never reuses another wiki token', async () => {
		const { probe, acquire } = setup();
		const r = await withRequestFields(
			{ runtimeToken: { value: 'other-secret', wikiKey: 'other' } },
			() => probe.inspect('test'),
		);
		expect(acquire).toHaveBeenCalledOnce();
		expect(r.extensions.has('test-token')).toBe(true);
		expect(r.extensions.has('other-secret')).toBe(false);
	});
	it('reports an unreachable wiki on sign-in failure without leaking details', async () => {
		const { probe, acquire, inspect } = setup();
		acquire.mockRejectedValue(new Error('secret'));
		expect(await probe.inspect('test')).toEqual({ reachable: false, extensions: new Set() });
		expect(inspect).not.toHaveBeenCalled();
	});
	it('delegates cache invalidation', () => {
		const { probe, invalidate } = setup();
		probe.invalidate('test');
		expect(invalidate).toHaveBeenCalledWith('test');
	});
});
