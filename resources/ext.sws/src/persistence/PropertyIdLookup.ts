import type { ExternalProperty } from '@/domain/PropertyDefinition';

export type IdSource = ExternalProperty['source'];
export interface IdSuggestion { id: string; label: string; description: string; url: string; datatype?: string; compatible?: boolean | null }

/** Sources share a selection contract; neither labels nor numeric IDs identify a source. */
export async function lookupPropertyIds(
	source: IdSource, query: string, mode: 'search' | 'resolve', signal: AbortSignal, fieldType?: string,
): Promise<IdSuggestion[]> {
	const url = new URL( mw.util.wikiScript( 'api' ), window.location.href );
	const normalized = /^p[1-9][0-9]*$/i.test( query.trim() ) ? query.trim().toUpperCase() : query.trim();
	url.search = new URLSearchParams( { action: 'swsbaseproperties', source, query: normalized, mode,
		language: String( mw.config.get( 'wgUserLanguage' ) || mw.config.get( 'wgContentLanguage' ) || 'de' ),
		...( fieldType ? { fieldtype: fieldType } : {} ), format: 'json', formatversion: '2' } ).toString();
	const controller = new AbortController();
	const abort = (): void => controller.abort();
	signal.addEventListener( 'abort', abort, { once: true } );
	if ( signal.aborted ) {
		controller.abort();
	}
	const timer = setTimeout( abort, 12000 );
	try {
		const response = await fetch( url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal } );
		const body = await response.json();
		const data = body.swsbaseproperties;
		if ( !response.ok || body.error || data?.version !== 1 || data.source !== source ||
			data.query !== normalized || data.mode !== mode || !Array.isArray( data.items ) || data.items.length > 10 ) {
			throw new Error( 'Property lookup unavailable' );
		}
		return data.items.map( ( item: IdSuggestion ) => {
			if ( typeof item.id !== 'string' || !/^P[1-9][0-9]*$/.test( item.id ) || item.id !== item.id.trim() ||
				typeof item.label !== 'string' || typeof item.description !== 'string' || typeof item.url !== 'string' ||
				( mode === 'resolve' && item.id !== normalized ) ) {
				throw new Error( 'Invalid property result' );
			}
			const target = new URL( item.url );
			if ( ![ 'https:', 'http:' ].includes( target.protocol ) || target.username || target.password ||
				( source === 'wikidata' ? target.href !== 'https://www.wikidata.org/wiki/Property:' + item.id :
					target.origin !== window.location.origin ) ) {
				throw new Error( 'Invalid property target' );
			}
			return { id: item.id, label: item.label || item.id, description: item.description, url: target.href,
                ...( typeof item.datatype === 'string' ? { datatype: item.datatype, compatible: item.compatible } : {} ) };
		} );
	} finally {
		clearTimeout( timer );
		signal.removeEventListener( 'abort', abort );
	}
}
