import { swsGetDataView } from './swsbase-get-data-view.js';
/** Optional read model; ordinary SWS installations keep their existing tool set. */
export const swsBasePack = {
    id: 'swsbase',
    extensionNames: ['SchemaBaseBridge'],
    tools: [swsGetDataView],
};
