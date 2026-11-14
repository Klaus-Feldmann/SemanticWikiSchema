import { describe, it, expect, vi } from 'vitest';
import { createMockMwn } from '../../../helpers/mock-mwn.ts';
import { contextOn } from '../../../helpers/fakeContext.ts';
import { contextReturning } from '../../../helpers/rawRequest.ts';
import { swsGetSchema } from '../../../../src/tools/extensions/sws/sws-get-schema.ts';
import { assertStructuredError } from '../../../helpers/structuredResult.ts';

describe('sws-get-schema', () => {
	it('normalizes propertyDefinitions to a properties array, preserving relation/select metadata', async () => {
		const mock = createMockMwn({
			rawRequest: vi.fn().mockResolvedValue({
				data: {
					schema: {
						description: 'A company.',
						propertyDefinitions: {
							'Main product': {
								type: 'relation',
								targetSchemas: ['Product'],
								multiple: false,
							},
							Status: { type: 'select', required: true, options: [{ id: 'o1', label: 'Active' }] },
						},
					},
				},
			}),
		});
		const ctx = contextOn(mock);
		const result = await swsGetSchema.handle({ name: 'Company' }, ctx);

		expect((mock.rawRequest.mock.calls[0][0] as { url: string }).url).toContain('/schema/Company');
		expect(result.structuredContent).toMatchObject({
			name: 'Company',
			description: 'A company.',
			properties: [
				{
					name: 'Main product',
					type: 'relation',
					targetSchemas: ['Product'],
				},
				{ name: 'Status', type: 'select', options: [{ id: 'o1', label: 'Active' }] },
			],
		});
	});

	it('says when the schema describes Dependent Subjects', async () => {
		const { ctx } = contextReturning({
			schema: { subjectsAreDependent: true, propertyDefinitions: {} },
		});
		const result = await swsGetSchema.handle({ name: 'Birth' }, ctx);

		expect(result.structuredContent).toMatchObject({ subjectsAreDependent: true });
	});

	it('leaves a schema of standalone subjects unflagged', async () => {
		const { ctx } = contextReturning({ schema: { propertyDefinitions: {} } });
		const result = await swsGetSchema.handle({ name: 'Person' }, ctx);

		expect(result.structuredContent).not.toHaveProperty('subjectsAreDependent');
	});

	it('returns not_found when the response has no schema', async () => {
		const mock = createMockMwn({ rawRequest: vi.fn().mockResolvedValue({ data: {} }) });
		const ctx = contextOn(mock);
		const result = await swsGetSchema.handle({ name: 'Nope' }, ctx);
		assertStructuredError(result, 'not_found');
	});

	it('URL-encodes the schema name', async () => {
		const mock = createMockMwn({ rawRequest: vi.fn().mockResolvedValue({ data: { schema: {} } }) });
		const ctx = contextOn(mock);
		await swsGetSchema.handle({ name: 'Schema Name' }, ctx);
		expect((mock.rawRequest.mock.calls[0][0] as { url: string }).url).toContain(
			'/schema/Schema%20Name',
		);
	});
});
