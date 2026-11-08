import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import ImagePreview from '@/components/Value/ImagePreview.vue';
import { resolveImage, type ImageInfo } from '@/persistence/ImageLookup';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn() } ) );
const image: ImageInfo = { reference: 'commons:Example.jpg', repository: 'commons', filename: 'Example.jpg',
	descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Example.jpg', thumbnailUrl: 'https://upload.wikimedia.org/thumb.jpg', width: 145, height: 150, mime: 'image/jpeg' };
describe( 'bounded image display', () => {
	beforeEach( () => {
		setupMwMock();
		vi.mocked( resolveImage ).mockReset().mockResolvedValue( image );
	} );
	it( 'uses repository thumbnail and links to the description page, not a local content page', async () => {
		const w = createTestWrapper( ImagePreview, { reference: image.reference } );
		await flushPromises();
		expect( w.get( 'a' ).attributes( 'href' ) ).toBe( image.descriptionUrl );
		expect( w.get( 'img' ).attributes( 'src' ) ).toBe( image.thumbnailUrl );
		expect( w.get( 'img' ).attributes( 'width' ) ).toBe( '145' );
		expect( w.get( 'img' ).attributes( 'height' ) ).toBe( '150' );
		expect( w.get( 'img' ).attributes( 'alt' ) ).toBe( image.filename );
		w.unmount();
	} );
	it( 'keeps readable file identity on outage, missing file and failed image download', async () => {
		vi.mocked( resolveImage ).mockRejectedValueOnce( new Error( 'offline' ) ).mockResolvedValueOnce( null ).mockResolvedValue( image );
		for ( let i = 0; i < 3; i++ ) {
			const w = createTestWrapper( ImagePreview, { reference: image.reference } );
			await flushPromises();
			if ( i === 2 ) {
				await w.get( 'img' ).trigger( 'error' );
				expect( w.get( 'a' ).attributes( 'href' ) ).toBe( image.descriptionUrl );
			}
			expect( w.text() ).toContain( 'Example.jpg' );
			expect( w.find( 'img' ).exists() ).toBe( false );
			w.unmount();
		}
	} );
	it( 'ignores an obsolete preview response after changing references', async () => {
		let finish!: ( value: ImageInfo ) => void;
		vi.mocked( resolveImage ).mockImplementationOnce( () => new Promise( ( resolve ) => {
			finish = resolve;
		} ) );
		const w = createTestWrapper( ImagePreview, { reference: image.reference } );
		await w.setProps( { reference: 'local:New.jpg' } );
		await flushPromises();
		finish( { ...image, filename: 'Obsolete.jpg' } );
		await flushPromises();
		expect( w.text() ).not.toContain( 'Obsolete.jpg' );
		w.unmount();
	} );
} );
