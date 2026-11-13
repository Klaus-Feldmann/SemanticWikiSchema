import type { PageTitleSearch, PageTitleResult } from '@/domain/PageTitleSearch';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

/** Shares the same explicit namespace lookup with the global PrefixSearch UI. */
export class RestPageTitleSearch implements PageTitleSearch {
	public constructor( _mediaWikiRestApiUrl: string, _httpClient: HttpClient ) {}

	public async searchPageTitles( search: string, limit: number, namespace?: number ): Promise<PageTitleResult[]> {
		await mw.loader.using( 'ext.sws.prefixSearch.api' );
		const api = mw.loader.require( 'ext.sws.prefixSearch.api' ) as {
			search: ( text: string, limit: number, namespace?: number ) => Promise<PageTitleResult[]>;
		};
		return api.search( search, limit, namespace );
	}
}
