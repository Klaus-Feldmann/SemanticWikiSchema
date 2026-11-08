import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { CdxSelect } from '@wikimedia/codex';
import { imageSourceOptions } from '@/presentation/imageSources';
import PropertyDefinitionEditor from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import PropertySourceEditor from '@/components/SchemaEditor/Property/PropertySourceEditor.vue';
import IdLookup from '@/components/Id/IdLookup.vue';
import ImageInput from '@/components/Value/ImageInput.vue';
import { type ImageProperty, ImageType } from '@/domain/propertyTypes/Image';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newStringValue } from '@/domain/Value';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/PropertyIdLookup', () => ( { lookupPropertyIds: vi.fn().mockResolvedValue( [ { id: 'P18', datatype: 'commonsMedia', compatible: true, label: 'Bild', description: '', url: 'https://www.wikidata.org/wiki/Property:P18' } ] ) } ) );
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn().mockResolvedValue( null ) } ) );
const property = (): ImageProperty => new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image' }, { imageSource: 'commons' } );
describe( 'four ordered image sources', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { config: { wgSWSProjectName: 'AufEinWort' } } );
		vi.useFakeTimers();
	} );
	afterEach( () => vi.useRealTimers() );
	it( 'uses project name and requested source order rather than a fixed local label', () => {
		expect( imageSourceOptions().map( ( item ) => item.label ) ).toEqual( [ 'Wikidata', 'Wikibase', 'AufEinWort', 'Commons' ] );
	} );
	it( 'saves a selected P18 under the chosen schema source and clears it on direct file source', async () => {
		const w = createTestWrapper( PropertyDefinitionEditor, { property: property(), otherPropertyNames: [] } );
		const source = w.findComponent( PropertySourceEditor );
		await source.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'wikidata' );
		const input = source.findComponent( IdLookup ).get( 'input' );
		await input.trigger( 'focus' );
		await input.setValue( 'P18' );
		await vi.advanceTimersByTimeAsync( 301 );
		await flushPromises();
		await input.trigger( 'keydown', { key: 'ArrowDown' } );
		await input.trigger( 'keydown', { key: 'Enter' } );
		await flushPromises();
		expect( w.emitted( 'update:property-definition' )?.slice( -1 )[ 0 ][ 0 ] ).toMatchObject( { imageSource: 'wikidata', externalProperty: { source: 'wikidata', id: 'P18' } } );
		expect( ( w.vm as unknown as { unparseableInputMessage(): string | null } ).unparseableInputMessage() ).toBeNull();
		await source.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'commons' );
		expect( w.emitted( 'update:property-definition' )?.slice( -1 )[ 0 ][ 0 ] ).toMatchObject( { imageSource: 'commons', externalProperty: undefined } );
		expect( source.findComponent( IdLookup ).exists() ).toBe( false );
		w.unmount();
	} );
	it( 'keeps own images and disables automatic choices until the explicit reference is removed', async () => {
		const w = createTestWrapper( ImageInput, { property: property(), modelValue: newStringValue( 'commons:Goethe.jpg' ) } );
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'wikibase-local' );
		expect( w.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'commons' );
		expect( w.findComponent( CdxSelect ).props( 'menuItems' ).find( ( item ) => item.value === 'wikibase-local' )?.disabled ).toBe( true );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		expect( ( w.vm as unknown as { getCurrentValue(): unknown } ).getCurrentValue() ).toEqual( newStringValue( 'commons:Goethe.jpg' ) );
		w.unmount();
	} );
} );
