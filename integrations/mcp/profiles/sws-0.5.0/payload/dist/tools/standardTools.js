import { getAgentContext } from './get-agent-context.js';
import { getSkillTopic } from './get-skill-topic.js';
import { extensionPacks } from './extensions/index.js';
import { getPage } from './get-page.js';
import { getPages } from './get-pages.js';
import { getPageHistory } from './get-page-history.js';
import { getRecentChanges } from './get-recent-changes.js';
import { searchPage } from './search-page.js';
import { searchPageByPrefix } from './search-page-by-prefix.js';
import { parseWikitext } from './parse-wikitext.js';
import { comparePages } from './compare-pages.js';
import { getFile } from './get-file.js';
import { getFileData } from './get-file-data.js';
import { getRevision } from './get-revision.js';
import { getSiteInfo } from './get-site-info.js';
import { getCategoryMembers } from './get-category-members.js';
import { getLinksHere } from './get-links-here.js';
import { getRandomPages } from './get-random-pages.js';
import { listWikis } from './list-wikis.js';
import { whoami } from './whoami.js';
import { createPage } from './create-page.js';
import { updatePage } from './update-page.js';
import { movePage } from './move-page.js';
import { deletePage } from './delete-page.js';
import { undeletePage } from './undelete-page.js';
import { protectPage } from './protect-page.js';
import { uploadFile } from './upload-file.js';
import { uploadFileFromUrl } from './upload-file-from-url.js';
import { updateFile } from './update-file.js';
import { updateFileFromUrl } from './update-file-from-url.js';
import { oauthStatus } from './oauth-status.js';
import { oauthLogout } from './oauth-logout.js';
// Every tool but the wiki-management ones: the core tools and each extension
// pack's. registerAllTools registers this list and the read-only gate reads it,
// so the two cannot disagree.
//
// `Tool<any>` widens the heterogeneous-schema array; `inputSchema: TSchema`
// is invariant in `TSchema`, so `Tool<never>` and `Tool<ZodRawShape>` both
// fail this assignment. The dispatcher's own generic re-narrows TSchema
// when each tool's handler is wrapped.
// oxlint-disable-next-line typescript/no-explicit-any
export const standardTools = [
    getAgentContext,
    getSkillTopic,
    getPage,
    getPages,
    getPageHistory,
    getRecentChanges,
    searchPage,
    searchPageByPrefix,
    parseWikitext,
    comparePages,
    getFile,
    getFileData,
    getRevision,
    getSiteInfo,
    getCategoryMembers,
    getLinksHere,
    getRandomPages,
    listWikis,
    whoami,
    createPage,
    updatePage,
    movePage,
    deletePage,
    undeletePage,
    protectPage,
    uploadFile,
    uploadFileFromUrl,
    updateFile,
    updateFileFromUrl,
    oauthStatus,
    oauthLogout,
    ...extensionPacks.flatMap((pack) => pack.tools),
];
