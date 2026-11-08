import type { ExtensionPack } from '../types.ts';
import { swsListSchemas } from './sws-list-schemas.ts';
import { swsGetSchema } from './sws-get-schema.ts';
import { swsCypherQuery } from './sws-cypher-query.ts';
import { swsSearchSubjects } from './sws-search-subjects.ts';
import { swsGetSubject } from './sws-get-subject.ts';
import { swsGetPageSubjects } from './sws-get-page-subjects.ts';
import { swsCreateSubject } from './sws-create-subject.ts';
import { swsUpdateSubject } from './sws-update-subject.ts';
import { swsDeleteSubject } from './sws-delete-subject.ts';
import { swsSetMainSubject } from './sws-set-main-subject.ts';
import { swsValidateSubject } from './sws-validate-subject.ts';
import { swsMintSubjectIds } from './sws-mint-subject-ids.ts';

export const swsPack: ExtensionPack = {
	id: 'sws',
	extensionNames: ['SemanticWikiSchema'],
	tools: [
		swsListSchemas,
		swsGetSchema,
		swsCypherQuery,
		swsSearchSubjects,
		swsGetSubject,
		swsGetPageSubjects,
		swsCreateSubject,
		swsUpdateSubject,
		swsDeleteSubject,
		swsSetMainSubject,
		swsValidateSubject,
		swsMintSubjectIds,
	],
};
