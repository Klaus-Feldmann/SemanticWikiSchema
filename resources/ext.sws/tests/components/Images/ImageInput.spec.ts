import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, type VueWrapper } from '@vue/test-utils';
import { CdxLookup, CdxSelect } from '@wikimedia/codex';
import ImageInput from '@/components/Value/ImageInput.vue';
import ImagePreview from '@/components/Value/ImagePreview.vue';
import { ImageType } from '@/domain/propertyTypes/Image';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newStringValue } from '@/domain/Value';
import { lookupImages, resolveImage, type ImageInfo } from '@/persistence/ImageLookup';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), lookupImages: vi.fn(), resolveImage: vi.fn() } ) );
const image: ImageInfo = { reference: 'commons:Example.jpg', repository: 'commons', filename: 'Example.jpg',
	descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Example.jpg', thumbnailUrl: 'https://upload.wikimedia.org/thumb.jpg', width: 145, height: 150, mime: 'image/jpeg' };
function show( multiple = false, stored: string | undefined = undefined ): VueWrapper {
	return createTestWrapper( ImageInput, { property: new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image' }, { multiple } ),
		modelValue: stored ? newStringValue( stored ) : undefined, label: 'Bild' } );
}
const exposed = ( w: VueWrapper ): ValueInputExposes => w.vm as unknown as ValueInputExposes;
describe( 'image selection using real Codex controls', () => {
	beforeEach( () => {
		setupMwMock();
		vi.useFakeTimers();
		vi.mocked( lookupImages ).mockReset().mockResolvedValue( [ image ] );
		vi.mocked( resolveImage ).mockReset().mockResolvedValue( image );
	} );
	afterEach( () => vi.useRealTimers() );
	it( 'selects a repository-qualified result with the keyboard and leaves save unblocked', async () => {
		const w = show();
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'commons' );
		const input = w.get( 'input' );
		await input.trigger( 'focus' );
		await input.setValue( 'Example' );
		expect( exposed( w ).unparseableInputMessage?.() ).not.toBeNull();
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		await vi.advanceTimersByTimeAsync( 301 );
		await flushPromises();
		expect( lookupImages ).toHaveBeenCalledWith( 'commons', 'Example', 'search', expect.any( AbortSignal ) );
		await input.trigger( 'keydown', { key: 'ArrowDown' } );
		await input.trigger( 'keydown', { key: 'Enter' } );
		await flushPromises();
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( image.reference ) );
		expect( exposed( w ).unparseableInputMessage?.() ).toBeNull();
		expect( w.findComponent( ImagePreview ).props( 'reference' ) ).toBe( image.reference );
		w.unmount();
	} );
	it( 'preserves persisted references without reselecting on reopen or metadata failure', async () => {
		vi.mocked( resolveImage ).mockRejectedValue( new Error( 'offline' ) );
		for ( let i = 0; i < 2; i++ ) {
			const w = show( false, image.reference );
			await flushPromises();
			expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( image.reference ) );
			expect( exposed( w ).unparseableInputMessage?.() ).toBeNull();
			expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
			w.unmount();
		}
	} );
	it( 'changes search repository without changing saved references', async () => {
		const w = show( true, 'local:Example.jpg' );
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'commons' );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'local:Example.jpg' ) );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'adds and removes individual images in a multi field', async () => {
		const w = show( true, 'local:Old.jpg' );
		await w.get( 'input' ).setValue( 'Example' );
		await vi.advanceTimersByTimeAsync( 301 );
		await w.findComponent( CdxLookup ).vm.$emit( 'update:selected', image.reference );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'local:Old.jpg', image.reference ) );
		await w.get( 'button' ).trigger( 'click' );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( image.reference ) );
		w.unmount();
	} );
	it( 'does not erase a stored image while typing or clearing an unconfirmed search', async () => {
		const w = show( false, image.reference );
		await w.get( 'input' ).setValue( 'something else' );
		expect( exposed( w ).unparseableInputMessage?.() ).not.toBeNull();
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( image.reference ) );
		await w.get( 'input' ).setValue( '' );
		expect( exposed( w ).unparseableInputMessage?.() ).toBeNull();
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'ignores late responses from the previous search repository', async () => {
		let finish!: ( items: ImageInfo[] ) => void;
		vi.mocked( lookupImages ).mockImplementationOnce( () => new Promise( ( resolve ) => {
			finish = resolve;
		} ) );
		const w = show();
		await w.get( 'input' ).setValue( 'Example' );
		await vi.advanceTimersByTimeAsync( 301 );
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'commons' );
		finish( [ { ...image, reference: 'local:Old.jpg' } ] );
		await flushPromises();
		expect( w.findComponent( CdxLookup ).props( 'menuItems' ) ).toEqual( [] );
		w.unmount();
	} );
} );
