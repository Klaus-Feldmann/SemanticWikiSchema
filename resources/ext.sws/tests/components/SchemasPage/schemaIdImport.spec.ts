import { webcrypto } from 'node:crypto';
import { TextEncoder } from 'node:util';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { importSchemaDocument } from '@/components/SchemasPage/schemaIdImport';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';

class ImportServer {
	public pages = new Map<string, { schema: any; revision: number }>();

	public requests = new Map<string, string>();

	public writes = 0;

	public failCompletion: string | null = null;

	public http: HttpClient = {
		post: async ( _url: string, body: any ) => {
			let id = this.requests.get( body.requestKey );
			if ( id === undefined ) {
				id = `S${ this.pages.size + 1 }`;
				const schema = JSON.parse( JSON.stringify( body.schema ) );
				schema.id = id;
				schema.formatVersion = 2;
				this.pages.set( id, { schema, revision: ++this.writes } );
				this.requests.set( body.requestKey, id );
			}
			return Response.json( { schema: this.pages.get( id )!.schema } );
		},
		get: async ( url: string ) => {
			const id = url.split( ':' ).slice( -1 )[ 0 ]!;
			const page = this.pages.get( id )!;
			return Response.json( { source: JSON.stringify( page.schema ), latest: { id: page.revision } } );
		},
		put: async ( url: string, body: any ) => {
			const id = url.split( '/' ).slice( -1 )[ 0 ]!;
			if ( this.failCompletion === id ) {
				this.failCompletion = null;
				return new Response( '', { status: 503 } );
			}
			if ( body.baseRevisionId !== this.pages.get( id )!.revision ) {
				return new Response( '', { status: 409 } );
			}
			this.pages.set( id, { schema: structuredClone( body.schema ), revision: ++this.writes } );
			return Response.json( {} );
		},
		patch: vi.fn(), delete: vi.fn(),
	};
}
const source = (): string => JSON.stringify( { schemas: {
	A: { labels: { de: 'Gleiches Label' }, identityLanguage: 'de', descriptions: {}, propertyDefinitions: {
		Partner: { type: 'relation', targetSchemas: [ 'B' ], multiple: true },
		Text: { type: 'text', default: 'B' },
	}, extra: { target: 'B' } },
	B: { labels: { de: 'Gleiches Label' }, identityLanguage: 'de', descriptions: {}, propertyDefinitions: {
		Zurück: { type: 'relation', targetSchema: 'A' },
	} },
} } );

describe( 'stable schema ID import', () => {
	beforeEach( () => {
		vi.stubGlobal( 'crypto', webcrypto );
		vi.stubGlobal( 'TextEncoder', TextEncoder );
	} );
	afterEach( () => vi.unstubAllGlobals() );

	it( 'allocates both schemas before resolving a cycle, preserving namesakes and original payload', async () => {
		const server = new ImportServer();
		const document = source();
		const result = await importSchemaDocument( document, [ 'A', 'B' ], '/rest', server.http, 'de' );
		expect( result.created ).toEqual( [ 'S1', 'S2' ] );
		const a = server.pages.get( 'S1' )!.schema;
		const b = server.pages.get( 'S2' )!.schema;
		expect( a.propertyDefinitions.Partner.targetSchemas ).toEqual( [ { id: 'S2' } ] );
		expect( b.propertyDefinitions.Zurück.targetSchema ).toEqual( { id: 'S1' } );
		expect( a.labels ).toEqual( b.labels );
		expect( a.extra.target ).toBe( 'B' );
		expect( a.propertyDefinitions.Text.default ).toBe( 'B' );
		expect( a.swsImport.originalSchema ).toEqual( JSON.parse( document ).schemas.A );
	} );

	it( 'repeating the same file neither replaces nor duplicates completed schemas', async () => {
		const server = new ImportServer();
		await importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' );
		const writes = server.writes;
		await importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' );
		expect( server.pages.size ).toBe( 2 );
		expect( server.writes ).toBe( writes );
	} );

	it( 'resumes an interrupted completion with the same identities', async () => {
		const server = new ImportServer();
		server.failCompletion = 'S2';
		await expect( importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' ) ).rejects.toThrow( 'completion failed' );
		await importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' );
		expect( server.pages.size ).toBe( 2 );
		expect( server.pages.get( 'S2' )!.schema.propertyDefinitions.Zurück.targetSchema ).toEqual( { id: 'S1' } );
	} );

	it( 'does not overwrite an independently edited import', async () => {
		const server = new ImportServer();
		await importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' );
		server.pages.get( 'S1' )!.schema.labels.de = 'Forschung';
		const writes = server.writes;
		await expect( importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' ) ).rejects.toThrow( 'independently edited' );
		expect( server.writes ).toBe( writes );
		expect( server.pages.get( 'S1' )!.schema.labels.de ).toBe( 'Forschung' );
	} );

	it( 'validates selection dependency closure before any write', async () => {
		const server = new ImportServer();
		await expect( importSchemaDocument( source(), [ 'A' ], '/rest', server.http, 'de' ) ).rejects.toThrow( 'Missing selected schema dependency' );
		expect( server.writes ).toBe( 0 );
	} );

	it( 'keeps explicit foreign references foreign', async () => {
		const server = new ImportServer();
		const document = JSON.stringify( { schemas: { A: { propertyDefinitions: {
			Remote: { type: 'relation', targetSchemas: [ { source: 'remote', name: 'B' } ] },
		} } } } );
		await importSchemaDocument( document, [ 'A' ], '/rest', server.http, 'de' );
		expect( server.pages.get( 'S1' )!.schema.propertyDefinitions.Remote.targetSchemas ).toEqual( [ { source: 'remote', name: 'B' } ] );
	} );

	it( 'retains original import provenance through normal schema editing', async () => {
		const server = new ImportServer();
		await importSchemaDocument( source(), [ 'A', 'B' ], '/rest', server.http, 'de' );
		const data = server.pages.get( 'S1' )!.schema;
		const schema = new SchemaDeserializer().deserialize( 'S1', data ).withDescription( 'Eigene Beschreibung' );
		expect( new SchemaSerializer().schemaToJson( schema ).swsImport ).toEqual( data.swsImport );
	} );
} );
