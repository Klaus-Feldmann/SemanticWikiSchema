/** Authenticated local title lookup. Shared by page inputs and batched displays. */
export interface LocalPageInfo { title: string; exists: boolean; valid: boolean }
export async function lookupLocalPages( titles: readonly string[] ): Promise<LocalPageInfo[]> {
	if ( titles.length === 0 ) {
		return [];
	}
	const url = new URL( mw.util.wikiScript( 'api' ), window.location.href );
	url.search = new URLSearchParams( { action: 'query', format: 'json', formatversion: '2',
		titles: titles.join( '|' ), prop: 'info' } ).toString();
	const response = await fetch( url, { credentials: 'same-origin' } );
	if ( !response.ok ) {
		throw new Error( 'Local page lookup failed' );
	}
	const data = await response.json();
	if ( data.error || !Array.isArray( data.query?.pages ) ) {
		throw new Error( 'Local page lookup failed' );
	}
	const normalized = new Map<string, string>( ( data.query.normalized || [] ).map( ( item: { from: string; to: string } ) => [ item.from, item.to ] ) );
	return titles.map( ( title ) => {
		const canonical = normalized.get( title ) || title;
		const page = data.query.pages.find( ( item: { title: string } ) => item.title === canonical );
		return { title: page?.title || canonical, valid: !!page && !page.invalid && page.ns >= 0,
			exists: !!page && !page.invalid && !page.missing && page.ns >= 0 };
	} );
}

// Batch one render's existence checks, keeping no persistent existence cache.
let waiting: { title: string; resolve: ( info: LocalPageInfo ) => void; reject: ( reason: unknown ) => void }[] = [];
let scheduled = false;
export function localPageInfo( title: string ): Promise<LocalPageInfo> {
	return new Promise( ( resolve, reject ) => {
		waiting.push( { title, resolve, reject } );
		if ( scheduled ) {
			return;
		}
		scheduled = true;
		queueMicrotask( async () => {
			scheduled = false;
			const requests = waiting;
			waiting = [];
			const unique = [ ...new Set( requests.map( ( request ) => request.title ) ) ];
			for ( let offset = 0; offset < unique.length; offset += 50 ) {
				const chunk = unique.slice( offset, offset + 50 );
				const group = requests.filter( ( request ) => chunk.includes( request.title ) );
				try {
					const results = await lookupLocalPages( chunk );
					for ( const request of group ) {
						request.resolve( results[ chunk.indexOf( request.title ) ] );
					}
				} catch ( error ) {
					for ( const request of group ) {
						request.reject( error );
					}
				}
			}
		} );
	} );
}

export function pageTitleInput( text: string, namespace?: number ): string | null {
	if ( /[|#\r\n]/.test( text ) || /^[a-z][a-z0-9+.-]*:\/\//i.test( text.trim() ) ) {
		return null;
	}
	const title = mw.Title.newFromText( text, namespace );
	return title && title.getNamespaceId() >= 0 ? title.getPrefixedText() : null;
}
