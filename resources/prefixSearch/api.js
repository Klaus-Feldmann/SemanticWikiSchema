'use strict';
/** Shared title lookup: preserve native completion, enrich metadata, then fill missing slots. */
function search( text, limit, namespace ) {
	const count = Math.max( 1, Math.min( limit, 20 ) );
	const api = new mw.Api();
	const metadata = {
		action: 'query', formatversion: 2,
		prop: 'info|pageprops|pageimages|description', inprop: 'url', ppprop: 'displaytitle',
		piprop: 'thumbnail', pithumbsize: 80, redirects: 1
	};
	const params = Object.assign( {}, metadata, {
		generator: 'prefixsearch', gpssearch: text, gpslimit: count
	} );
	if ( namespace !== undefined && namespace !== null ) { params.gpsnamespace = namespace; }
	function results( data ) {
		if ( data.error ) { throw new Error( data.error.info || 'Title search failed' ); }
		return ( data.query && data.query.pages || [] )
			.filter( ( page ) => namespace === undefined || namespace === null || page.ns === namespace )
			.sort( ( a, b ) => ( a.index || 0 ) - ( b.index || 0 ) )
			.map( ( page ) => {
				const node = new DOMParser().parseFromString(
					page.pageprops && page.pageprops.displaytitle || '', 'text/html' ).body;
				const redirect = ( data.query.redirects || [] ).find( ( entry ) =>
					entry.to === page.title && ( entry.index === undefined || entry.index === page.index ) );
				return { pageId: page.pageid, title: page.title,
					displayTitle: node.textContent || page.title, url: page.fullurl,
					description: page.description,
					thumbnail: page.thumbnail ? { url: page.thumbnail.source,
						width: page.thumbnail.width, height: page.thumbnail.height } : undefined,
					redirectFrom: redirect && redirect.from };
			} );
	}
	return api.get( params ).then( async ( data ) => {
		const primary = results( data );
		const term = text.trim();
		const enabled = !mw.config || mw.config.get( 'wgSWSPrefixSearchSubstringFallback' ) !== false;
		if ( !enabled || primary.length >= count || term.length < 3 || term.length > 255 || namespace === undefined || namespace === null ) {
			return primary.slice( 0, count );
		}
		// Literal input, not user-supplied regex or search syntax. Explicit namespace bounds cost.
		const escaped = term.replace( /[\\^$.*+?()[\]{}|<>@#&~"/]/g, '\\$&' ).replace( /[\r\n]/g, ' ' );
		try {
			const extraData = await api.get( Object.assign( {}, metadata, {
				generator: 'search', gsrsearch: 'intitle:/' + escaped + '/i',
				gsrnamespace: namespace, gsrlimit: 20
			} ) );
			if ( extraData.warnings ) { throw new Error( 'Supplemental query was not fully applied' ); }
			const extra = results( extraData );
			const seen = new Set( primary.map( ( page ) => page.pageId ) );
			for ( const page of extra ) {
				if ( !seen.has( page.pageId ) ) { primary.push( page ); seen.add( page.pageId ); }
			}
		} catch ( error ) {
			// Regex support is optional; never discard working native suggestions on a fallback failure.
			if ( mw.log && mw.log.warn ) { mw.log.warn( 'SWS PrefixSearch: supplemental title search unavailable' ); }
		}
		return primary.slice( 0, count );
	} );
}
module.exports = { search };
