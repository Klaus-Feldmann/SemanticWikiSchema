/** Own identity metadata, independent of source descriptions and page titles. */
export interface LocalizedIdentity {
	identityLanguage: string;
	labels: Record<string, string>;
	descriptions: Record<string, string>;
}

export function identityFromJson( data: Record<string, unknown> ): LocalizedIdentity | null {
	if ( data.identityLanguage === undefined && data.labels === undefined && data.descriptions === undefined ) {
		return null;
	}
	if ( typeof data.identityLanguage !== 'string' || !isTerms( data.labels ) || !isTerms( data.descriptions ) ) {
		throw new Error( 'Incomplete identity metadata' );
	}
	return { identityLanguage: data.identityLanguage, labels: { ...data.labels }, descriptions: { ...data.descriptions } };
}
function isTerms( value: unknown ): value is Record<string, string> {
	return value !== null && typeof value === 'object' && !Array.isArray( value ) && Object.values( value ).every( ( term ) => typeof term === 'string' );
}
export function identityLabel( identity: LocalizedIdentity | null, fallback: string, language?: string ): string {
	if ( identity === null ) {
		return fallback;
	}
	const lang = language ?? ( typeof mw === 'undefined' ? identity.identityLanguage : mw.config?.get( 'wgUserLanguage' ) );
	return identity.labels[ lang ] || identity.labels[ identity.identityLanguage ] || identity.labels.en || fallback;
}
export function identityDescription( identity: LocalizedIdentity | null, fallback?: string, language?: string ): string {
	if ( identity === null ) {
		return fallback ?? '';
	}
	const lang = language ?? ( typeof mw === 'undefined' ? identity.identityLanguage : mw.config?.get( 'wgUserLanguage' ) );
	return identity.descriptions[ lang ] || identity.descriptions[ identity.identityLanguage ] || identity.descriptions.en || fallback || '';
}
export function identityWithLabel( identity: LocalizedIdentity | null, label: string | null ): LocalizedIdentity | null {
	return identity === null ? null : { ...identity, labels: { ...identity.labels, [ identity.identityLanguage ]: label ?? '' } };
}
