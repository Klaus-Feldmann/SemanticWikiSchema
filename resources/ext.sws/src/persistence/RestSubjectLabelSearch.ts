import type { SubjectLabelSearch, SubjectLabelResult } from '@/domain/SubjectLabelSearch';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

export class RestSubjectLabelSearch implements SubjectLabelSearch {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
	) {
	}

	public async searchSubjectLabels( search: string, schemas: readonly string[] | undefined ): Promise<SubjectLabelResult[]> {
		const params = new URLSearchParams( { search } );
		const language = typeof mw === 'undefined' ? undefined : mw.config?.get( 'wgUserLanguage' );
		if ( typeof language === 'string' && language !== '' ) {
			params.set( 'language', language );
		}

		// Explicit current page keys avoid confusing a new S-ID with an old same-spelled name.
		// The endpoint reads an absent parameter as every Schema. No Schema name holds the separator,
		// as no page title does.
		if ( schemas !== undefined ) {
			params.set( 'schemaKey', schemas.join( '|' ) );
		}

		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/sws/v0/subject-labels?${ params.toString() }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error searching subject labels' );
		}

		return await response.json();
	}

}
