import { swsGetQueryCapabilities } from './sws-get-query-capabilities.js';
import { swsSparqlQuery } from './sws-sparql-query.js';
import { swsQueryEntities } from './sws-query-entities.js';
import { swsGetProcessingStatus } from './sws-get-processing-status.js';
import { swsListSchemas } from './sws-list-schemas.js';
import { swsGetSchema } from './sws-get-schema.js';
import { swsCypherQuery } from './sws-cypher-query.js';
import { swsSearchSubjects } from './sws-search-subjects.js';
import { swsGetSubject } from './sws-get-subject.js';
import { swsGetPageSubjects } from './sws-get-page-subjects.js';
import { swsCreateSubject } from './sws-create-subject.js';
import { swsUpdateSubject } from './sws-update-subject.js';
import { swsDeleteSubject } from './sws-delete-subject.js';
import { swsSetMainSubject } from './sws-set-main-subject.js';
import { swsValidateSubject } from './sws-validate-subject.js';
import { swsMintSubjectIds } from './sws-mint-subject-ids.js';
export const swsPack = {
    id: 'sws',
    extensionNames: ['SemanticWikiSchema'],
    wikiGate: {
        tools: ['sws-cypher-query', 'sws-sparql-query'],
        isSatisfied: (identity, toolName) => identity.swsQueryBackends?.[toolName === 'sws-cypher-query' ? 'cypher' : 'sparql'] !==
            'not-configured',
        refusal: (key) => `SWS graph query backend is not configured on wiki ${key}.`,
    },
    errorCodes: {
        'swsprocessing-too-large': 'invalid_input',
        'swsprocessing-invalid-target': 'invalid_input',
        'swsprocessing-unavailable': 'upstream_failure',
    },
    tools: [
        swsGetQueryCapabilities,
        swsSparqlQuery,
        swsQueryEntities,
        swsListSchemas,
        swsGetProcessingStatus,
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
