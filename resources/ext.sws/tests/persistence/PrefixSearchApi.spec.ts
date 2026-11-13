import { readFileSync } from 'node:fs';
import { afterEach, describe, expect, it, vi } from 'vitest';
const api = new Function( 'module', readFileSync( process.cwd() + '/../prefixSearch/api.js', 'utf8' ) + '\nreturn module.exports;' )( { exports: {} } );
afterEach( () => vi.unstubAllGlobals() );
describe( 'PrefixSearch Action API', () => {
	it( 'resolves the matched redirect and preserves ranking', async () => {
		const get = vi.fn().mockResolvedValue( { query: {
			redirects: [ { index: 1, from: 'Goethe', to: 'Johann Wolfgang von Goethe' } ],
			pages: [ { pageid: 2, ns: 0, title: 'Goethe als Freimaurer', index: 2 },
				{ pageid: 1, ns: 0, title: 'Johann Wolfgang von Goethe', index: 1, description: 'Dichter' } ]
		} } );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
		const result = await api.search( 'Goethe', 10, 0 );
		expect( get.mock.calls[ 0 ][ 0 ] ).toMatchObject( { redirects: 1, prop: 'info|pageprops|pageimages|description', gpslimit: 10 } );
		expect( result.map( ( p: { title: string } ) => p.title ) ).toEqual( [ 'Johann Wolfgang von Goethe', 'Goethe als Freimaurer' ] );
		expect( result[ 0 ].redirectFrom ).toBe( 'Goethe' );
	} );
	it.each( [ 0, 14 ] )( 'enforces namespace %s, keeps canonical names and strips display markup', async ( namespace ) => {
		const get = vi.fn().mockResolvedValue( { query: { pages: [
			{ pageid: 1, ns: namespace, title: 'Canonical', index: 1, description: 'Untertitel', thumbnail: { source: 'https://example.org/image.jpg', width: 80, height: 90 }, pageprops: { displaytitle: '<b>Anzeige</b>' } },
			{ pageid: 2, ns: 102, title: 'Wrong' }
		] } } );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
				const result = await api.search( 'a', 100, namespace );
		expect( get.mock.calls[ 0 ][ 0 ] ).toMatchObject( { gpsnamespace: namespace, gpslimit: 20 } );
		expect( result ).toHaveLength( 1 );
		expect( result[ 0 ] ).toMatchObject( { title: 'Canonical', displayTitle: 'Anzeige', description: 'Untertitel', thumbnail: { url: 'https://example.org/image.jpg', width: 80, height: 90 } } );
	} );
	it( 'reports failures', async () => {
		vi.stubGlobal( 'mw', { Api: class { get = () => Promise.resolve( { error: { info: 'Denied' } } ); } } );
		await expect( api.search( 'x', 10, 14 ) ).rejects.toThrow( 'Denied' );
	} );
} );

describe( 'Supplemental title substring lookup', () => {
	it( 'fills slots with title-only substring matches without displacing native results', async () => {
		const get = vi.fn().mockResolvedValueOnce( { query: { pages: [ { pageid: 1, ns: 14, title: 'Kategorie:Wissenschaft' } ] } } )
			.mockResolvedValueOnce( { query: { pages: [ { pageid: 1, ns: 14, title: 'Kategorie:Wissenschaft' }, { pageid: 2, ns: 14, title: 'Kategorie:Sprachwissenschaft' } ] } } );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
		const pages = await api.search( 'wissenschaft', 10, 14 );
		expect( pages.map( ( p: { pageId: number } ) => p.pageId ) ).toEqual( [ 1, 2 ] );
		expect( get.mock.calls[ 1 ][ 0 ] ).toMatchObject( { generator: 'search', gsrsearch: 'intitle:/wissenschaft/i', gsrnamespace: 14 } );
	} );
	it( 'does not supplement short terms or an already full list', async () => {
		const get = vi.fn().mockResolvedValue( { query: { pages: [ { pageid: 1, ns: 14, title: 'Kategorie:A' } ] } } );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
		await api.search( 'ab', 10, 14 ); expect( get ).toHaveBeenCalledTimes( 1 );
		get.mockClear(); await api.search( 'abc', 1, 14 ); expect( get ).toHaveBeenCalledTimes( 1 );
	} );
	it( 'preserves primary results if regex is disabled or rejected', async () => {
		const get = vi.fn().mockResolvedValueOnce( { query: { pages: [ { pageid: 1, ns: 14, title: 'Kategorie:Original' } ] } } )
			.mockResolvedValueOnce( { warnings: { search: 'regex disabled' }, query: { pages: [ { pageid: 2, ns: 14, title: 'Unrelated' } ] } } );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
		expect( ( await api.search( 'abc', 10, 14 ) ).map( ( p: { pageId: number } ) => p.pageId ) ).toEqual( [ 1 ] );
	} );
	it( 'escapes regex punctuation in the input', async () => {
		const get = vi.fn().mockResolvedValue( {} );
		vi.stubGlobal( 'mw', { Api: class { get = get; } } );
		await api.search( 'A.b', 10, 14 );
		expect( get.mock.calls[ 1 ][ 0 ].gsrsearch ).toBe( 'intitle:/A\\.b/i' );
	} );
	it( 'can be disabled independently of the main search', async () => {
		const get = vi.fn().mockResolvedValue( {} );
		vi.stubGlobal( 'mw', { Api: class { get = get; }, config: { get: () => false } } );
		await api.search( 'abc', 10, 14 ); expect( get ).toHaveBeenCalledTimes( 1 );
	} );
} );
