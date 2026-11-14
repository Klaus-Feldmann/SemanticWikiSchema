import { describe, it, expect, vi } from 'vitest';
import { z } from 'zod';
import { createMockMwn } from '../../../helpers/mock-mwn.ts';
import { contextOn } from '../../../helpers/fakeContext.ts';
import { swsCreateSubject } from '../../../../src/tools/extensions/sws/sws-create-subject.ts';
import { swsUpdateSubject } from '../../../../src/tools/extensions/sws/sws-update-subject.ts';
import { flattenSubject } from '../../../../src/tools/extensions/sws/subjects.ts';

const notes = { Name: 'Author note' };
function context() {
	const mock = createMockMwn({
		getCsrfToken: vi.fn().mockResolvedValue('csrf'),
		rawRequest: vi.fn().mockImplementation(async (spec) =>
			spec.method === 'GET'
				? { data: { subjects: { test: { id: 'test' } } } }
				: {
						data: {
							status: 'saved',
							subjectId: 'test',
							subject: { id: 'test', fieldNotes: notes },
						},
					},
		),
	});
	return { mock, ctx: contextOn(mock) };
}
describe('SWS field annotations', () => {
	it('sends and returns field annotations when creating', async () => {
		const { mock, ctx } = context();
		const result = await swsCreateSubject.handle(
			{ pageId: 7, schema: 'Test', statements: {}, fieldNotes: notes },
			ctx,
		);
		expect(JSON.parse(mock.rawRequest.mock.calls.at(-1)![0].data as string).fieldNotes).toEqual(
			notes,
		);
		expect(result.structuredContent).toMatchObject({ subject: { fieldNotes: notes } });
	});
	it('preserves omitted annotations and distinguishes explicit clearing', async () => {
		for (const fieldNotes of [undefined, {}, notes]) {
			const { mock, ctx } = context();
			await swsUpdateSubject.handle(
				{ id: 'test', statements: {}, ...(fieldNotes === undefined ? {} : { fieldNotes }) },
				ctx,
			);
			const body = JSON.parse(mock.rawRequest.mock.calls.at(-1)![0].data as string) as Record<
				string,
				unknown
			>;
			expect(Object.hasOwn(body, 'fieldNotes')).toBe(fieldNotes !== undefined);
			if (fieldNotes !== undefined) expect(body.fieldNotes).toEqual(fieldNotes);
		}
	});
	it('passes dependent annotations in the host write', async () => {
		const { mock, ctx } = context();
		await swsCreateSubject.handle(
			{
				pageId: 7,
				schema: 'Test',
				statements: {},
				dependentSubjects: [
					{ id: 'dependent', schema: 'Detail', statements: {}, fieldNotes: notes },
				],
			},
			ctx,
		);
		expect(
			JSON.parse(mock.rawRequest.mock.calls.at(-1)![0].data as string).dependentSubjects[0]
				.fieldNotes,
		).toEqual(notes);
	});
	it('rejects malformed annotation values before a request', () => {
		expect(
			z
				.object(swsUpdateSubject.inputSchema)
				.safeParse({ id: 'test', statements: {}, fieldNotes: { Name: 42 } }).success,
		).toBe(false);
		expect(
			z
				.object(swsUpdateSubject.inputSchema)
				.safeParse({ id: 'test', statements: {}, fieldNotes: { '': 'note' } }).success,
		).toBe(false);
	});
	it('preserves empty and missing note maps distinctly in read results', () => {
		expect(flattenSubject({ fieldNotes: {} }, 'test')).toHaveProperty('fieldNotes', {});
		expect(flattenSubject({}, 'test')).not.toHaveProperty('fieldNotes');
	});
});
