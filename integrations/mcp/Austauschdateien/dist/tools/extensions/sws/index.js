import { swsListSchemas } from "./sws-list-schemas.js";
import { swsGetSchema } from "./sws-get-schema.js";
import { swsCypherQuery } from "./sws-cypher-query.js";
import { swsSearchSubjects } from "./sws-search-subjects.js";
import { swsGetSubject } from "./sws-get-subject.js";
import { swsGetPageSubjects } from "./sws-get-page-subjects.js";
import { swsCreateSubject } from "./sws-create-subject.js";
import { swsUpdateSubject } from "./sws-update-subject.js";
import { swsDeleteSubject } from "./sws-delete-subject.js";
import { swsSetMainSubject } from "./sws-set-main-subject.js";
import { swsValidateSubject } from "./sws-validate-subject.js";
import { swsMintSubjectIds } from "./sws-mint-subject-ids.js";
export const swsPack = {
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
