import { parseImageReference } from '@/persistence/ImageLookup';
import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue';
import type { Subject } from '@/domain/Subject';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface DatePart { text: string; yearTitle?: string }
export interface ExternalEntityValue { kind: 'entity' | 'text' | 'image' | 'time' | 'novalue' | 'somevalue'; value?: string; language?: string; rank?: string; statementId?: string | null; parts?: DatePart[]; calendar?: string; precision?: number; year?: number; month?: number; day?: number; displayDate?: { year: number; month?: number; day?: number }; sourceTime?: Record<string, unknown>; id?: string; label?: string; url?: string; reference?: string; filename?: string; repository?: string }
export interface ExternalField {
	name: string; source: 'wikidata' | 'wikibase-local'; propertyId: string;
	propertyLabel?: string; propertyUrl?: string | null;
	status: 'resolved' | 'missing' | 'unavailable' | 'unsupported' | 'manual'; values: ExternalEntityValue[];
	sourceStatus?: ExternalField['status']; retained?: boolean; recorded?: boolean; storageMode?: 'store';
	language?: string | null; languageFallback?: boolean; lastAttemptedAt?: string; lastSuccessfulAt?: string;
}
function safeLink( source: string, id: string, value: unknown, property: boolean ): value is string {
	if ( typeof value !== 'string' ) {
		return false;
	}
	try {
		const url = new URL( value );
		return !url.username && !url.password && [ 'https:', 'http:' ].includes( url.protocol ) &&
			( source === 'wikidata' ? url.href === 'https://www.wikidata.org/wiki/' + ( property ? 'Property:' : '' ) + id :
				url.origin === window.location.origin );
	} catch {
		return false;
	}
}
export function useExternalStatements( subject: Ref<Subject>, definitions: Ref<PropertyDefinition[]> ): {
	fields: Ref<Map<string, ExternalField>>; loading: Ref<boolean>; failed: Ref<boolean>;
} {
	const fields = ref( new Map<string, ExternalField>() );
	const loading = ref( false );
	const failed = ref( false );
	const signature = computed( () => JSON.stringify( definitions.value.map( ( p ) => [ p.name.toString(), p.type, p.externalProperty ] ) ) );
	let controller: AbortController | null = null;
	let sequence = 0;
	onBeforeUnmount( () => {
		sequence++;
		controller?.abort();
	} );
	watch( () => [ subject.value.getId().text, subject.value.getPageIdentifiers().getPageId(), signature.value ], async () => {
		const ticket = ++sequence;
		controller?.abort();
		fields.value = new Map();
		loading.value = false;
		failed.value = false;
		const pageId = subject.value.getPageIdentifiers().getPageId();
		const sid = subject.value.getId().text;
		const params = new URLSearchParams( window.location.search );
		const historical = params.has( 'oldid' ) || params.has( 'diff' ) || !!mw.config.get( 'wgDiffOldId' ) ||
			( !!mw.config.get( 'wgRevisionId' ) && !!mw.config.get( 'wgCurRevisionId' ) && mw.config.get( 'wgRevisionId' ) !== mw.config.get( 'wgCurRevisionId' ) );
		const bound = definitions.value.filter( ( p ) => p.externalProperty );
		if ( !pageId || bound.length === 0 || historical ) {
			return;
		}
		const current = new AbortController();
		controller = current;
		const timeout = setTimeout( () => current.abort(), 25000 );
		loading.value = true;
		try {
			const url = new URL( mw.util.wikiScript( 'api' ), window.location.href );
			url.search = new URLSearchParams( { action: 'swsbasevalues', pageid: String( pageId ), subject: sid,
				language: String( mw.config.get( 'wgUserLanguage' ) || mw.config.get( 'wgContentLanguage' ) || 'de' ),
				format: 'json', formatversion: '2' } ).toString();
			const response = await fetch( url, { credentials: 'same-origin', cache: 'no-store', signal: current.signal } );
			const body = await response.json();
			const data = body.swsbasevalues;
			if ( !response.ok || body.error || data?.version !== 1 || data.scope !== 'current' || data.pageId !== pageId || !Array.isArray( data.fields ) ) {
				throw new Error( 'External values unavailable' );
			}
			if ( data.mainSubjectId !== sid ) {
				return;
			}
			const result = new Map<string, ExternalField>();
			for ( const field of data.fields as ExternalField[] ) {
				const definition = bound.find( ( p ) => p.name.toString() === field.name );
				if ( !definition || field.source !== definition.externalProperty?.source || field.propertyId !== definition.externalProperty.id ) {
					continue; // Schema changed while the request was in flight.
				}
				if ( ![ 'resolved', 'missing', 'unavailable', 'unsupported', 'manual' ].includes( field.status ) || !Array.isArray( field.values ) ||
					( field.propertyLabel !== undefined && typeof field.propertyLabel !== 'string' ) ||
					( field.propertyUrl && !safeLink( field.source, field.propertyId, field.propertyUrl, true ) ) ) {
					throw new Error( 'Invalid derived property' );
				}
				if ( ( field.sourceStatus !== undefined && ![ 'resolved', 'missing', 'unavailable', 'unsupported', 'manual' ].includes( field.sourceStatus ) ) ||
					[ field.retained, field.recorded, field.languageFallback ].some( ( value ) => value !== undefined && typeof value !== 'boolean' ) ||
					( field.language !== undefined && field.language !== null && !/^[a-z]{2,12}(?:-[a-z0-9]{1,12})*$/.test( field.language ) ) ) {
					throw new Error( 'Invalid stored source metadata' );
				}
				for ( const value of field.values ) {
					if ( ![ 'entity', 'text', 'image', 'time', 'novalue', 'somevalue' ].includes( value.kind ) || ( value.kind === 'entity' &&
						( typeof value.id !== 'string' || !/^Q[1-9][0-9]*$/.test( value.id ) || value.id !== value.id.trim() ||
							typeof value.label !== 'string' || !safeLink( field.source, value.id, value.url, false ) ) ) ) {
						throw new Error( 'Invalid derived value' );
					}
                    if ( value.kind === 'text' && ( typeof value.value !== 'string' || !value.value.trim() ||
                        ( value.language !== undefined && !/^[a-z][a-z0-9-]{0,39}$/.test( value.language ) ) ) ) {
                        throw new Error( 'Invalid derived text' );
                    }
                    if ( value.kind === 'time' && ( definition.type !== 'historicalDate' ||
						!Array.isArray( value.parts ) || value.parts.length === 0 || value.parts.length > 3 ||
						!value.parts.every( ( part ) => typeof part.text === 'string' && part.text.length <= 128 &&
							( part.yearTitle === undefined || /^[1-9][0-9]{0,3}(?: BC| v\. Chr\.)?$/.test( part.yearTitle ) ) ) ||
						!value.sourceTime || ![ 'julian', 'gregorian' ].includes( value.calendar || '' ) ||
						![ 9, 10, 11 ].includes( value.precision || 0 ) ) ) {
						throw new Error( 'Invalid derived time' );
					}
					if ( value.kind === 'image' && ( definition.type !== 'image' || typeof value.reference !== 'string' ||
						parseImageReference( value.reference )?.repository !== 'commons' || value.repository !== 'commons' ||
						parseImageReference( value.reference )?.filename !== value.filename ) ) {
						throw new Error( 'Invalid derived image' );
					}
				}
				result.set( field.name, field );
			}
			if ( ticket === sequence ) {
				fields.value = result;
			}
		} catch {
			if ( ticket === sequence ) {
				fields.value = new Map( bound.map( ( p ) => [ p.name.toString(), { name: p.name.toString(), source: p.externalProperty!.source, propertyId: p.externalProperty!.id, status: 'unavailable' as const, values: [] } ] ) );
                failed.value = false;
			}
		} finally {
			clearTimeout( timeout );
			if ( ticket === sequence ) {
				loading.value = false;
			}
		}
	}, { immediate: true } );
	return { fields, loading, failed };
}
