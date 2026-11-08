import { describe, it, expect, vi, afterEach } from 'vitest';
import { createMockMwn } from '../../../helpers/mock-mwn.ts';
import { contextOn } from '../../../helpers/fakeContext.ts';
import { swsGetDataView as tool } from '../../../../src/tools/extensions/swsbase/swsbase-get-data-view.ts';
import { swsBasePack } from '../../../../src/tools/extensions/swsbase/index.ts';
import { assertStructuredError } from '../../../helpers/structuredResult.ts';
import { z } from 'zod';

const view = () => ({
	version: 1,
	readOnly: true,
	complete: true,
	page: { id: 42, title: 'Person' },
	identities: { wikidata: { id: 'Q5' } },
	subjects: [
		{
			id: 'sMain',
			fields: [
				{
					name: 'Klasse',
					ownValues: [],
					external: { source: 'wikidata', values: [{ id: 'Q5', label: 'Mensch' }] },
					effectiveValues: [{ id: 'Q5', label: 'Mensch' }],
				},
				{ name: 'Datum', ownValues: [{ value: '-0347', calendar: 'julian', precision: 'year' }] },
				{ name: 'Zahl', ownValues: [{ value: 0 }, { value: false }] },
			],
		},
	],
});
function setup(data: unknown = { swsbasedataview: view() }) {
	const mock = createMockMwn({ request: vi.fn().mockResolvedValue(data) });
	return { mock, ctx: contextOn(mock) };
}
afterEach(() => vi.unstubAllEnvs());
describe('swsbase-get-data-view', () => {
	it('preserves the complete typed payload without writes', async () => {
		const { mock, ctx } = setup();
		const r = await tool.handle({ pageId: 42, language: 'de' }, ctx);
		expect(r.structuredContent).toEqual({ ...view(), selection: { subject: null, fields: null } });
		expect(mock.request).toHaveBeenCalledExactlyOnceWith({
			action: 'swsbasedataview',
			pageid: 42,
			language: 'de',
			format: 'json',
			formatversion: 2,
		});
	});
	it('resolves titles using the existing authenticated context', async () => {
		const { mock, ctx } = setup();
		mock.request.mockResolvedValueOnce({ query: { pages: [{ pageid: 42 }] } });
		const r = await tool.handle({ title: 'Person' }, ctx);
		expect(r.isError).not.toBe(true);
		expect(mock.request).toHaveBeenCalledTimes(2);
	});
	it.each([{}, { title: 'X', pageId: 42 }])(
		'rejects ambiguous/missing page references',
		async (args) => {
			const { mock, ctx } = setup();
			assertStructuredError(await tool.handle(args, ctx), 'invalid_input');
			expect(mock.request).not.toHaveBeenCalled();
		},
	);
	it('reports a missing title', async () => {
		const { ctx } = setup({ query: { pages: [{ missing: true }] } });
		assertStructuredError(await tool.handle({ title: 'Missing' }, ctx), 'not_found');
	});
	it('preserves incomplete status and supports field projection', async () => {
		const v = { ...view(), complete: false };
		const { ctx } = setup({ swsbasedataview: v });
		const r = await tool.handle({ pageId: 42, fields: ['Datum'] }, ctx);
		expect(r.structuredContent).toMatchObject({
			complete: false,
			selection: { fields: ['Datum'] },
			subjects: [{ fields: [v.subjects[0]!.fields[1]] }],
		});
	});
	it('rejects unknown fields instead of silently returning nothing', async () => {
		const { ctx } = setup();
		assertStructuredError(
			await tool.handle({ pageId: 42, fields: ['Typo'] }, ctx),
			'invalid_input',
		);
	});
	it('passes source/authentication failures to the central dispatcher', async () => {
		const { mock, ctx } = setup();
		mock.request.mockRejectedValueOnce(new Error('permissiondenied'));
		await expect(tool.handle({ pageId: 42 }, ctx)).rejects.toThrow('permissiondenied');
	});
	it.each([
		{},
		{ swsbasedataview: { ...view(), version: 2 } },
		{ swsbasedataview: { ...view(), page: { id: 99 } } },
	])('rejects incompatible or mismatched results', async (data) => {
		const { ctx } = setup(data);
		assertStructuredError(await tool.handle({ pageId: 42 }, ctx), 'upstream_failure');
	});
	it('checks subject selection against the response', async () => {
		const { ctx } = setup();
		expect((await tool.handle({ pageId: 42, subject: 'sMain' }, ctx)).isError).not.toBe(true);
		assertStructuredError(
			await tool.handle({ pageId: 42, subject: 'sOther' }, ctx),
			'upstream_failure',
		);
	});
	it('refuses oversized data explicitly and allows a narrower retry', async () => {
		vi.stubEnv('MCP_CONTENT_MAX_BYTES', '1000');
		const v = view();
		v.subjects[0]!.fields[0]!.ownValues = [];
		const huge = {
			...v,
			subjects: [
				{
					id: 'sMain',
					fields: [
						{ name: 'Long', ownValues: [{ value: 'ä'.repeat(2000) }] },
						{ name: 'Small', ownValues: [{ value: 0 }] },
					],
				},
			],
		};
		const { ctx } = setup({ swsbasedataview: huge });
		assertStructuredError(await tool.handle({ pageId: 42 }, ctx), 'invalid_input');
		expect((await tool.handle({ pageId: 42, fields: ['Small'] }, ctx)).isError).not.toBe(true);
	});
	it('validates language and input types at registration boundary', () => {
		const schema = z.object(tool.inputSchema);
		expect(schema.safeParse({ pageId: 0 }).success).toBe(false);
		expect(schema.safeParse({ language: '../../' }).success).toBe(false);
		expect(schema.safeParse({ language: 'en-gb' }).success).toBe(true);
	});
	it('gates only the new tool on Bridge availability', () => {
		expect(swsBasePack.extensionNames).toEqual(['SWSBaseBridge', 'SchemaBaseBridge']);
		expect(swsBasePack.tools).toEqual([tool]);
		expect(tool.annotations?.readOnlyHint).toBe(true);
	});
});
