export type ImageRepository = 'local' | 'commons';
export interface ImageInfo {
	reference: string;
	repository: ImageRepository;
	filename: string;
	descriptionUrl: string;
	thumbnailUrl: string | null;
	width: number;
	height: number;
	mime: string;
}
export function parseImageReference( reference: string ): { repository: ImageRepository; filename: string } | null {
	const colon = reference.indexOf( ':' );
	const repository = reference.slice( 0, colon );
	const filename = reference.slice( colon + 1 );
	return colon > 0 && ( repository === 'local' || repository === 'commons' ) && filename !== '' && Array.from( filename ).length <= 500 && filename === filename.trim() &&
		!filename.startsWith( ':' ) && !Array.from( filename ).some( ( char ) => char.charCodeAt( 0 ) < 32 || char.charCodeAt( 0 ) === 127 ) && !/[[\]{}<>|#]/u.test( filename ) ? { repository, filename } : null;
}
function safeUrl( value: unknown, repository: ImageRepository ): value is string {
	if ( typeof value !== 'string' ) {
		return false;
	}
	try {
		const url = new URL( value );
		return [ 'https:', 'http:' ].includes( url.protocol ) && !url.username && !url.password &&
			( repository !== 'commons' || [ 'commons.wikimedia.org', 'upload.wikimedia.org', 'thumb.wikimedia.org' ].includes( url.hostname ) );
	} catch {
		return false;
	}
}
export async function lookupImages( repository: ImageRepository, query: string, mode: 'search' | 'resolve', signal?: AbortSignal ): Promise<ImageInfo[]> {
	const url = new URL( mw.util.wikiScript( 'api' ), window.location.href );
	url.search = new URLSearchParams( { action: 'swsimages', repository, query: query.trim(), mode, format: 'json', formatversion: '2' } ).toString();
	const controller = new AbortController();
	const abort = (): void => controller.abort();
	signal?.addEventListener( 'abort', abort, { once: true } );
	if ( signal?.aborted ) {
		controller.abort();
	}
	const timer = setTimeout( abort, 20000 );
	try {
		const response = await fetch( url, { credentials: 'same-origin', cache: 'no-store', signal: controller.signal } );
		const body = await response.json();
		const data = body.swsimages;
		if ( !response.ok || body.error || data?.version !== 1 || data.repository !== repository || data.mode !== mode || data.query !== query.trim() || !Array.isArray( data.items ) || data.items.length > 10 ) {
			throw new Error( 'Image lookup unavailable' );
		}
		return data.items.map( ( item: ImageInfo ) => {
			const reference = typeof item.reference === 'string' ? parseImageReference( item.reference ) : null;
			if ( !reference || reference.repository !== repository || reference.filename !== item.filename || item.repository !== repository ||
				!safeUrl( item.descriptionUrl, repository ) || typeof item.mime !== 'string' || !item.mime.startsWith( 'image/' ) ||
				( item.thumbnailUrl !== null && ( !safeUrl( item.thumbnailUrl, repository ) || !Number.isInteger( item.width ) ||
					!Number.isInteger( item.height ) || item.width < 1 || item.height < 1 || item.width > 150 || item.height > 150 ) ) ) {
				throw new Error( 'Invalid image result' );
			}
			return item;
		} );
	} finally {
		clearTimeout( timer );
		signal?.removeEventListener( 'abort', abort );
	}
}
const previews = new Map<string, { expires: number; promise: Promise<ImageInfo | null> }>();
export function resolveImage( reference: string ): Promise<ImageInfo | null> {
	const parsed = parseImageReference( reference );
	if ( !parsed ) {
		return Promise.resolve( null );
	}
	const old = previews.get( reference );
	if ( old && old.expires > Date.now() ) {
		return old.promise;
	}
	const promise = lookupImages( parsed.repository, parsed.filename, 'resolve' ).then( ( items ) => items[ 0 ] ?? null ).catch( ( error ) => {
		previews.delete( reference );
		throw error;
	} );
	previews.set( reference, { expires: Date.now() + 60000, promise } );
	return promise;
}
