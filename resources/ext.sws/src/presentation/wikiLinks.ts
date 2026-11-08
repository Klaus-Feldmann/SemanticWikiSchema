/** Shared by value and label renderers; never interprets values as HTML or wikitext. */
export function wikiPageUrl( title: string, namespace = '' ): string | null {
	const target = ( namespace ? namespace + ':' : '' ) + title.trim();
	if ( !target || /[[\]{}|<>\r\n]/.test( target ) || target.startsWith( ':' ) || /^(?:https?|javascript|data):/i.test( target ) ) {
		return null;
	}
	return mw.util.getUrl( target );
}
