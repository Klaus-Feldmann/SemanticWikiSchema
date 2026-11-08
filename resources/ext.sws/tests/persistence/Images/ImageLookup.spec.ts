import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { lookupImages, parseImageReference, resolveImage, type ImageInfo } from '@/persistence/ImageLookup';
import { setupMwMock } from '../../VueTestHelpers';
const image: ImageInfo = { reference: 'commons:Example.jpg', repository: 'commons', filename: 'Example.jpg',
	descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Example.jpg', thumbnailUrl: 'https://upload.wikimedia.org/thumb.jpg', width: 145, height: 150, mime: 'image/jpeg' };
const response = ( item: ImageInfo, query = 'Example', mode = 'search' ): { ok: boolean; json: () => Promise<unknown> } => ( { ok: true, json: async () => ( {
	swsimages: { version: 1, repository: item.repository, mode, query, items: [ item ] },
} ) } );
describe( 'source-qualified image lookup contract', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'util' ] } );
		mw.util.wikiScript = () => '/w/api.php';
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'preserves repository and colon inside a filename', () => {
		expect( parseImageReference( 'commons:Ä: Test.svg' ) ).toEqual( { repository: 'commons', filename: 'Ä: Test.svg' } );
		for ( const ref of [ 'Example.jpg', 'remote:X.jpg', 'local:', 'local: X.jpg', 'local:X#Y.jpg', 'local:<x>.jpg', 'local:X\nY.jpg' ] ) {
			expect( parseImageReference( ref ) ).toBeNull();
		}
	} );
	it( 'uses authenticated same-origin API and retains canonical identity', async () => {
		const fetcher = vi.fn().mockResolvedValue( response( image ) );
		vi.stubGlobal( 'fetch', fetcher );
		expect( await lookupImages( 'commons', 'Example', 'search' ) ).toEqual( [ image ] );
		const [ url, options ] = fetcher.mock.calls[ 0 ];
		expect( url.searchParams.get( 'action' ) ).toBe( 'swsimages' );
		expect( options.credentials ).toBe( 'same-origin' );
		expect( options.cache ).toBe( 'no-store' );
	} );
	it.each( [
		{ descriptionUrl: 'javascript:alert(1)' }, // eslint-disable-line no-script-url
		{ thumbnailUrl: 'https://evil.example/X.jpg' },
		{ width: 151 },
		{ height: 151 },
		{ reference: 'local:Example.jpg' },
		{ mime: 'application/pdf' },
	] )( 'rejects invalid returned image metadata %j', async ( override ) => {
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( response( { ...image, ...override } ) ) );
		await expect( lookupImages( 'commons', 'Example', 'search' ) ).rejects.toThrow();
	} );
	it( 'keeps local repository metadata separate from an equal Commons filename', async () => {
		const local: ImageInfo = { ...image, reference: 'local:Example.jpg', repository: 'local', descriptionUrl: window.location.origin + '/wiki/Datei:Example.jpg' };
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( response( local ) ) );
		expect( ( await lookupImages( 'local', 'Example', 'search' ) )[ 0 ].reference ).toBe( 'local:Example.jpg' );
	} );
	it( 'batches concurrent preview reads and never substitutes source on a failed request', async () => {
		const fetcher = vi.fn().mockResolvedValue( response( { ...image, reference: 'commons:Concurrent.jpg', filename: 'Concurrent.jpg' }, 'Concurrent.jpg', 'resolve' ) );
		vi.stubGlobal( 'fetch', fetcher );
		const results = await Promise.all( [ resolveImage( 'commons:Concurrent.jpg' ), resolveImage( 'commons:Concurrent.jpg' ) ] );
		expect( fetcher ).toHaveBeenCalledTimes( 1 );
		expect( results[ 0 ] ).toEqual( results[ 1 ] );
		fetcher.mockResolvedValue( { ok: false, json: async () => ( {} ) } );
		await expect( resolveImage( 'local:Retry.jpg' ) ).rejects.toThrow();
		await expect( resolveImage( 'local:Retry.jpg' ) ).rejects.toThrow();
		expect( fetcher ).toHaveBeenCalledTimes( 3 );
	} );
	it( 'accepts the actual Wikimedia thumbnail server for Goethe in search and resolve responses', async () => {
		const goethe = { ...image, thumbnailUrl: 'https://thumb.wikimedia.org/wikipedia/commons/thumb/0/0e/Goethe.jpg/250px-Goethe.jpg', width: 122, height: 150 };
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( response( goethe ) ) );
		expect( ( await lookupImages( 'commons', 'Example', 'search' ) )[ 0 ].thumbnailUrl ).toContain( 'thumb.wikimedia.org' );
	} );

} );
