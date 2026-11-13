import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { normalizeSchemaName } from '@/stores/SchemaStore';

// Raw schema exports may contain plugin fields; retain them without narrowing or rebuilding them.
// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Json = Record<string, any>;
export interface SchemaIdImportResult { created: string[] }

function object( value: unknown ): value is Json {
	return typeof value === 'object' && value !== null && !Array.isArray( value );
}
function stable( value: unknown ): string {
	if ( Array.isArray( value ) ) {
		return `[${ value.map( stable ).join( ',' ) }]`;
	}
	if ( object( value ) ) {
		return `{${ Object.keys( value ).sort().map( ( key ) => `${ JSON.stringify( key ) }:${ stable( value[ key ] ) }` ).join( ',' ) }}`;
	}
	return JSON.stringify( value );
}
async function digest( text: string ): Promise<string> {
	const bytes = await crypto.subtle.digest( 'SHA-256', new TextEncoder().encode( text ) );
	return Array.from( new Uint8Array( bytes ), ( value ) => value.toString( 16 ).padStart( 2, '0' ) ).join( '' );
}

/** Preserve the original file/entry; reserve/create all identities before rewriting any dependency. */
export async function importSchemaDocument(
	document: string, selected: string[], restUrl: string, http: HttpClient,
	language: string,
): Promise<SchemaIdImportResult> {
	const file = JSON.parse( document );
	if ( !object( file ) || !object( file.schemas ) || selected.length === 0 || selected.length > 1000 ) {
		throw new Error( 'Invalid or empty schema import' );
	}
	const normalized = new Map<string, string>();
	for ( const name of selected ) {
		if ( !object( file.schemas[ name ] ) || !object( file.schemas[ name ].propertyDefinitions ) ) {
			throw new Error( `Invalid schema: ${ name }` );
		}
		const key = normalizeSchemaName( name );
		if ( normalized.has( key ) ) {
			throw new Error( `Ambiguous schema key: ${ name }` );
		}
		normalized.set( key, name );
	}
	const resolveName = ( reference: unknown ): string | null => {
		if ( object( reference ) && reference.source ) {
			return null; // Explicit other-source references retain their identity.
		}
		const name = typeof reference === 'string' ? reference : object( reference ) ? reference.id ?? reference.name : null;
		if ( typeof name !== 'string' || !normalized.has( normalizeSchemaName( name ) ) ) {
			throw new Error( `Missing selected schema dependency: ${ String( name ) }` );
		}
		const key = normalized.get( normalizeSchemaName( name ) )!;
		if ( object( reference ) && reference.id && file.schemas[ key ].id !== reference.id ) {
			throw new Error( `Schema ID is not a legacy name: ${ name }` );
		}
		return key;
	};
	// Validate dependency closure before reserving or creating anything.
	for ( const name of selected ) {
		for ( const property of Object.values( file.schemas[ name ].propertyDefinitions ) as Json[] ) {
			if ( property.type !== 'relation' ) {
				continue;
			}
			for ( const reference of property.targetSchemas ?? [ property.targetSchema ] ) {
				resolveName( reference );
			}
			if ( property.default?.some( ( value: Json ) => !value.target?.includes( ':' ) ) ) {
				throw new Error( 'Local entity defaults require explicit review; this imports schemas only' );
			}
		}
	}
	const documentHash = await digest( document );
	const ids = new Map<string, string>();
	const shells = new Map<string, Json>();
	const actual = new Map<string, { schema: Json; revision: number }>();
	for ( const name of selected ) {
		const source = file.schemas[ name ];
		const shell: Json = {
			...source,
			identityLanguage: source.identityLanguage ?? language,
			labels: source.labels ?? { [ language ]: source.label ?? name },
			descriptions: source.descriptions ?? ( source.description ? { [ language ]: source.description } : {} ),
			propertyDefinitions: {},
			swsImport: { documentHash, sourceKey: name, originalSchema: source },
		};
		delete shell.id;
		delete shell.formatVersion;
		delete shell.label;
		const reply = await http.post( `${ restUrl }/sws/v0/schema`, {
			schema: shell, requestKey: `schema-import:${ documentHash }:${ await digest( name ) }`,
		} );
		if ( !reply.ok ) {
			throw new Error( `Schema reservation/creation failed: ${ name }` );
		}
		const result = await reply.json();
		if ( !/^S[1-9][0-9]{0,17}$/.test( result.schema?.id ) ) {
			throw new Error( 'Import did not receive a stable schema ID' );
		}
		const id = result.schema.id;
		ids.set( name, id );
		shells.set( name, { ...shell, formatVersion: 2, id } );
		const page = await http.get( `${ restUrl }/v1/page/Schema:${ id }`, { headers: { 'Cache-Control': 'no-cache' } } );
		if ( !page.ok ) {
			throw new Error( `Cannot verify import page: ${ id }` );
		}
		const data = await page.json();
		actual.set( name, { schema: JSON.parse( data.source ), revision: data.latest?.id } );
	}
	const planned = new Map<string, Json>();
	for ( const name of selected ) {
		const definitions = structuredClone( file.schemas[ name ].propertyDefinitions );
		const rewrite = ( reference: unknown ): unknown => {
			const key = resolveName( reference );
			return key === null ? reference : { id: ids.get( key )! };
		};
		for ( const property of Object.values( definitions ) as Json[] ) {
			if ( property.type !== 'relation' ) {
				continue;
			}
			if ( property.targetSchemas ) {
				property.targetSchemas = property.targetSchemas.map( rewrite );
			} else {
				property.targetSchema = rewrite( property.targetSchema );
			}
		}
		const final = { ...shells.get( name )!, propertyDefinitions: definitions };
		const prior = actual.get( name )!;
		if ( stable( prior.schema ) !== stable( shells.get( name ) ) && stable( prior.schema ) !== stable( final ) ) {
			throw new Error( `Imported schema was independently edited; not replaced: ${ ids.get( name ) }` );
		}
		if ( !Number.isSafeInteger( prior.revision ) || prior.revision < 1 ) {
			throw new Error( 'Missing import base revision' );
		}
		planned.set( name, final );
	}
	// Do not complete another schema if an already imported one failed its verification.
	for ( const name of selected ) {
		const prior = actual.get( name )!;
		if ( stable( prior.schema ) === stable( planned.get( name ) ) ) {
			continue;
		}
		const reply = await http.put( `${ restUrl }/sws/v0/schema/${ ids.get( name )! }`, {
			schema: planned.get( name ), baseRevisionId: prior.revision,
		} );
		if ( !reply.ok ) {
			throw new Error( `Schema completion failed; repeat the same file to resume: ${ name }` );
		}
	}
	return { created: selected.map( ( name ) => ids.get( name )! ) };
}
