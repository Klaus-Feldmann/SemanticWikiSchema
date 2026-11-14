import type { Mwn } from 'mwn';
import { ClassifiedError } from '../../../errors/classifyError.ts';
import { swsRequest } from './swsRequest.ts';
import type { SubjectData } from './subjects.ts';

interface IdentityInput {
	identityLanguage?: string;
	labels?: Record<string, string>;
	descriptions?: Record<string, string>;
}

/** Keep omitted localized metadata while SWS validates a complete v2 identity. */
export async function updateIdentity(
	mwn: Mwn,
	id: string,
	input: IdentityInput,
	label?: string,
): Promise<IdentityInput> {
	if (
		input.identityLanguage !== undefined &&
		input.labels !== undefined &&
		input.descriptions !== undefined
	)
		return input;
	// oxlint-disable-next-line typescript/no-unsafe-type-assertion -- SWS response boundary
	const data = (await swsRequest(mwn, {
		method: 'GET',
		path: `/subject/${encodeURIComponent(id)}`,
		query: { latest: '1' },
	})) as { requestedId?: string; subjects?: Record<string, SubjectData> };
	const current = data.subjects?.[data.requestedId ?? id];
	if (current === undefined)
		throw new ClassifiedError('not_found', `SWS subject "${id}" not found`);
	if (current.identityLanguage === undefined) return input;
	const language = input.identityLanguage ?? current.identityLanguage;
	const labels = { ...(input.labels ?? current.labels ?? {}) };
	if (input.labels === undefined) {
		if (label === undefined || label.trim() === '') delete labels[language];
		else labels[language] = label;
	}
	return {
		identityLanguage: language,
		labels,
		descriptions: input.descriptions ?? current.descriptions ?? {},
	};
}
