import { describe, it, expect, vi } from 'vitest';
import { createMockMwn } from '../../../helpers/mock-mwn.ts';
import { contextOn } from '../../../helpers/fakeContext.ts';
import { swsMintSubjectIds } from '../../../../src/tools/extensions/sws/sws-mint-subject-ids.ts';

describe('sws-mint-subject-ids', () => {
	it('mints the requested number of subject IDs with a CSRF token and write classification', async () => {
		const mock = createMockMwn({
			getCsrfToken: vi.fn().mockResolvedValue('csrf-token'),
			rawRequest: vi
				.fn()
				.mockResolvedValue({ data: { subjectIds: ['s1demo6sssssss1', 's1demo6sssssss2'] } }),
		});
		const ctx = contextOn(mock);
		const result = await swsMintSubjectIds.handle({ count: 2 }, ctx);

		const call = mock.rawRequest.mock.calls[0][0] as {
			url: string;
			method: string;
			data: string;
			headers: Record<string, string>;
		};
		expect(call.url).toBe('https://test.wiki/w/rest.php/sws/v0/subject-ids');
		expect(call.method).toBe('POST');
		expect(JSON.parse(call.data)).toEqual({ count: 2 });
		expect(call.headers['X-CSRF-TOKEN']).toBe('csrf-token');
		expect(swsMintSubjectIds.annotations?.readOnlyHint).toBe(false);
		expect(swsMintSubjectIds.annotations?.idempotentHint).toBe(false);
		expect(result.structuredContent).toEqual({
			subjectIds: ['s1demo6sssssss1', 's1demo6sssssss2'],
		});
	});
});
