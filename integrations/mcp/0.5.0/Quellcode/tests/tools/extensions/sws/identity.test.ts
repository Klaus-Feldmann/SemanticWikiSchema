import { describe, it, expect, vi } from 'vitest';
import { createMockMwn } from '../../../helpers/mock-mwn.ts';
import { contextOn } from '../../../helpers/fakeContext.ts';
import { swsUpdateSubject } from '../../../../src/tools/extensions/sws/sws-update-subject.ts';
import { swsCreateSubject } from '../../../../src/tools/extensions/sws/sws-create-subject.ts';
import { swsGetSubject } from '../../../../src/tools/extensions/sws/sws-get-subject.ts';
import { swsGetSchema } from '../../../../src/tools/extensions/sws/sws-get-schema.ts';
import { flattenSubject } from '../../../../src/tools/extensions/sws/subjects.ts';
const identity = {
	identityLanguage: 'de',
	labels: { de: 'Eigener Name', en: 'Own name' },
	descriptions: { de: 'Eigene Beschreibung' },
};
function context(data: unknown) {
	const mock = createMockMwn({
		getCsrfToken: vi.fn().mockResolvedValue('csrf'),
		rawRequest: vi.fn().mockResolvedValue({ data }),
	});
	return { mock, ctx: contextOn(mock) };
}
describe('SWS 0.5.0 identity and field-key contract', () => {
	it('retains identity and field-key metadata in a read', () => {
		expect(
			flattenSubject(
				{
					id: 'E123',
					...identity,
					fieldKeyVersion: 3,
					displayNameIsGenerated: false,
					pageNamespaceId: 7480,
				},
				'E123',
			),
		).toMatchObject({
			id: 'E123',
			...identity,
			fieldKeyVersion: 3,
			displayNameIsGenerated: false,
			pageNamespaceId: 7480,
		});
	});
	it('sends the read field-key version and own identity on replacement', async () => {
		const { mock, ctx } = context({ status: 'saved' });
		await swsUpdateSubject.handle(
			{ id: 'E123', statements: {}, fieldKeyVersion: 3, ...identity },
			ctx,
		);
		expect(JSON.parse(mock.rawRequest.mock.calls[0][0].data as string)).toMatchObject({
			fieldKeyVersion: 3,
			...identity,
		});
	});
	it('supports creating a preallocated persistent Subject ID', async () => {
		const { mock, ctx } = context({ status: 'created', subjectId: 'E123' });
		await swsCreateSubject.handle(
			{ id: 'E123', pageId: 7, schema: 'S1', statements: {}, ...identity },
			ctx,
		);
		expect(JSON.parse(mock.rawRequest.mock.calls[0][0].data as string)).toMatchObject({
			id: 'E123',
			...identity,
		});
	});
	it('requests the latest editable revision explicitly', async () => {
		const { mock, ctx } = context({ subjects: { E123: { id: 'E123' } } });
		await swsGetSubject.handle({ id: 'E123', latest: true }, ctx);
		expect(mock.rawRequest.mock.calls[0][0].url).toContain('latest=1');
	});
	it('preserves Schema identity and field rename metadata', async () => {
		const schema = {
			formatVersion: 2,
			id: 'S1',
			...identity,
			fieldKeyChanges: [{ from: 'Old', to: 'New' }],
			propertyDefinitions: { New: { type: 'text' } },
		};
		const { ctx } = context({ schema });
		const result = await swsGetSchema.handle({ name: 'S1' }, ctx);
		expect(result.structuredContent).toMatchObject({
			id: 'S1',
			...identity,
			fieldKeyChanges: schema.fieldKeyChanges,
		});
	});
});

describe('SWS identity preservation during partial updates', () => {
	it('reads the latest identity before changing a label, retaining other languages', async () => {
		const mock = createMockMwn({
			getCsrfToken: vi.fn().mockResolvedValue('csrf'),
			rawRequest: vi
				.fn()
				.mockResolvedValueOnce({ data: { subjects: { E123: { id: 'E123', ...identity } } } })
				.mockResolvedValueOnce({ data: { status: 'saved' } }),
		});
		await swsUpdateSubject.handle(
			{ id: 'E123', label: 'Neuer Name', statements: {} },
			contextOn(mock),
		);
		const body = JSON.parse(mock.rawRequest.mock.calls[1][0].data as string);
		expect(body).toMatchObject({
			identityLanguage: 'de',
			labels: { de: 'Neuer Name', en: 'Own name' },
			descriptions: identity.descriptions,
		});
		expect(mock.rawRequest.mock.calls[0][0].url).toContain('latest=1');
	});
	it('does not write if the preservation read fails', async () => {
		const mock = createMockMwn({
			rawRequest: vi.fn().mockRejectedValue(new Error('network failure')),
		});
		await expect(
			swsUpdateSubject.handle({ id: 'E123', statements: {} }, contextOn(mock)),
		).rejects.toThrow();
		expect(mock.rawRequest).toHaveBeenCalledOnce();
	});
});

describe('SWS identity defaults on create', () => {
	it('supplies complete identity metadata when the caller provides only a label', async () => {
		const { mock, ctx } = context({ status: 'created', subjectId: 'E123' });
		await swsCreateSubject.handle({ pageId: 7, schema: 'S1', label: 'Test', statements: {} }, ctx);
		expect(JSON.parse(mock.rawRequest.mock.calls[0][0].data as string)).toMatchObject({
			identityLanguage: 'en',
			labels: { en: 'Test' },
			descriptions: {},
		});
	});
});
