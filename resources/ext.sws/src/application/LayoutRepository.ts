import type { LayoutLookup } from '@/application/LayoutLookup';
import type { Layout } from '@/domain/Layout';

export interface LayoutRepository extends LayoutLookup {

	saveLayout( layout: Layout, comment?: string ): Promise<void>;

}
