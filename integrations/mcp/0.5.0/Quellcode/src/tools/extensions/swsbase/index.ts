import type { ExtensionPack } from '../types.ts';
import { swsGetDataView } from './swsbase-get-data-view.ts';

/** Optional read model; ordinary SWS installations keep their existing tool set. */
export const swsBasePack: ExtensionPack = {
	id: 'swsbase',
	extensionNames: ['SchemaBaseBridge'],
	tools: [swsGetDataView],
};
