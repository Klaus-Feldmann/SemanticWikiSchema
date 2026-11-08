export interface DescriptionSource { source: 'wikidata' | 'wikibase-local' | 'page'; id: string; url: string }
export interface EntityDescription { text: string; language: string; sources: DescriptionSource[] }

/** Display-only data: source links must already match the validated page identities. */
export function readDescriptionGroups( raw: unknown, identities: Record<string, { status: string; id: string | null; url: string | null }>, page?: { id: number; url: string } ): EntityDescription[] {
	if ( !Array.isArray( raw ) ) {
		return [];
	}
	return raw.filter( ( group ): group is EntityDescription => {
		if ( !group || typeof group.text !== 'string' || !group.text.trim() || typeof group.language !== 'string' ||
   !group.language.split( '-' ).every( ( part: string, index: number ) => index === 0 ? /^[a-z]{2,12}$/.test( part ) : /^[a-z0-9]{1,12}$/.test( part ) ) || !Array.isArray( group.sources ) || !group.sources.length ) {
			return false;
		}
		return group.sources.every( ( item: DescriptionSource ) => {
			if ( item?.source === 'page' ) {
				if ( !page || item.id !== String( page.id ) || item.url !== page.url ) {
					return false;
				}
				try {
					const url = new URL( page.url );
					return [ 'http:', 'https:' ].includes( url.protocol ) && !url.username && !url.password && url.origin === window.location.origin;
				} catch {
					return false;
				}
			}
			return item && [ 'wikidata', 'wikibase-local' ].includes( item.source ) &&
				identities[ item.source ]?.status === 'resolved' && item.id === identities[ item.source ].id && item.url === identities[ item.source ].url;
		} );
	} );
}
