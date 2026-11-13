import { afterEach, describe, expect, it, vi } from 'vitest';
import { RestPageTitleSearch } from '@/persistence/RestPageTitleSearch';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';
afterEach( () => vi.unstubAllGlobals() );
describe( 'Shared page title adapter', () => {
	it.each( [ 0, 14, undefined ] )( 'forwards namespace %s and preserves canonical identity', async ( namespace ) => {
		const pages = [ { pageId: 1, title: 'Canonical', displayTitle: 'Anzeige' } ];
		const search = vi.fn().mockResolvedValue( pages );
		vi.stubGlobal( 'mw', { loader: { using: vi.fn().mockResolvedValue( undefined ), require: () => ( { search } ) } } );
		const adapter = new RestPageTitleSearch( '/rest.php', new InMemoryHttpClient( {} ) );
		expect( await adapter.searchPageTitles( 'a', 10, namespace ) ).toEqual( pages );
		expect( search ).toHaveBeenCalledWith( 'a', 10, namespace );
	} );
	it( 'propagates failures', async () => {
		vi.stubGlobal( 'mw', { loader: { using: vi.fn().mockRejectedValue( new Error( 'Failed' ) ) } } );
		await expect( new RestPageTitleSearch( '', new InMemoryHttpClient( {} ) ).searchPageTitles( 'x', 10 ) ).rejects.toThrow( 'Failed' );
	} );
} );
