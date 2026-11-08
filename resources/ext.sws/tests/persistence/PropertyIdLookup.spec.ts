import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { lookupPropertyIds } from '@/persistence/PropertyIdLookup';
import { setupMwMock } from '../VueTestHelpers';
const item = { id: 'P31', label: 'ist ein(e)', description: 'Klasse', url: 'https://www.wikidata.org/wiki/Property:P31' };
function reply( items = [ item ], extra = {} ): void {
	vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( {
		swsbaseproperties: { version: 1, source: 'wikidata', query: 'P31', mode: 'search', items, ...extra },
	} ) } ) );
}
describe( 'property lookup transport', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'util' ] } );
		mw.util.wikiScript = () => '/w/api.php';
		reply();
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'uses the same-origin read API and normalizes an ID', async () => {
		expect( await lookupPropertyIds( 'wikidata', 'p31', 'search', new AbortController().signal ) ).toEqual( [ item ] );
		const [ url, options ] = vi.mocked( fetch ).mock.calls[ 0 ];
		expect( new URL( String( url ) ).searchParams.get( 'source' ) ).toBe( 'wikidata' );
		expect( options?.credentials ).toBe( 'same-origin' );
	} );
	it( 'rejects a response from another source', async () => {
		reply( [ item ], { source: 'wikibase-local' } );
		await expect( lookupPropertyIds( 'wikidata', 'P31', 'search', new AbortController().signal ) ).rejects.toThrow();
	} );
	it( 'rejects an unsafe or source-confused link', async () => {
		// eslint-disable-next-line no-script-url -- Reject unsafe API data.
		reply( [ { ...item, url: 'javascript:alert(1)' } ] );
		await expect( lookupPropertyIds( 'wikidata', 'P31', 'search', new AbortController().signal ) ).rejects.toThrow();
	} );
	it( 'does not accept an item ID as a property', async () => {
		reply( [ { ...item, id: 'Q31' } ] );
		await expect( lookupPropertyIds( 'wikidata', 'P31', 'search', new AbortController().signal ) ).rejects.toThrow();
	} );
	it( 'keeps empty results distinct from a failed request', async () => {
		reply( [] );
		expect( await lookupPropertyIds( 'wikidata', 'P31', 'search', new AbortController().signal ) ).toEqual( [] );
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: false, json: async () => ( {} ) } ) );
		await expect( lookupPropertyIds( 'wikidata', 'P31', 'search', new AbortController().signal ) ).rejects.toThrow();
	} );
} );
