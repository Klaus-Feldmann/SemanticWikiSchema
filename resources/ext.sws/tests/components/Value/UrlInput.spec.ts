import type { VueWrapper } from '@vue/test-utils';
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { CdxTextInput, CdxField, CdxButton } from '@wikimedia/codex';
import UrlInput from '@/components/Value/UrlInput.vue';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
import { newStringValue, newUrlValue, valueToJson } from '@/domain/Value';
import { newUrlProperty } from '@/domain/propertyTypes/Url';
import { createTestWrapper } from '../../VueTestHelpers';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
import type { UrlValue } from '@/domain/Value';
beforeEach( () => vi.stubGlobal( 'mw', { msg: ( key: string ) => key, message: ( key: string ) => ( { text: () => key } ), util: { getUrl: ( title: string ) => '/wiki/' + encodeURIComponent( title ) } } ) );
afterEach( () => vi.unstubAllGlobals() );
function wrapper( value = newUrlValue( [] ), multiple = false ): VueWrapper<InstanceType<typeof UrlInput>> {
	return createTestWrapper( UrlInput, { modelValue: value, property: newUrlProperty( { name: 'Source', multiple } ), label: 'Source' } );
}
describe( 'Structured URL input', () => {
	it( 'shows one empty entry with four input fields', () => {
		const w = wrapper();
		expect( w.findAllComponents( CdxTextInput ) ).toHaveLength( 4 );
	} );
	it( 'preserves all parts after reopening', () => {
		const entry = { url: 'https://example.org/?a=1&b=2#x', label: 'Article', caption: 'Website', captionPage: 'Vermerk:Quelle' };
		const w = wrapper( newUrlValue( [ entry ] ) );
		expect( valueToJson( ( w.vm as unknown as ValueInputExposes ).getCurrentValue()! ) ).toEqual( [ entry ] );
		expect( w.findAllComponents( CdxTextInput ).map( ( i ) => i.props( 'modelValue' ) ) ).toEqual( Object.values( entry ) );
	} );
	it( 'changes the label without replacing the URL', async () => {
		const w = wrapper( newUrlValue( [ 'https://example.org' ] ) );
		w.findAllComponents( CdxTextInput )[ 1 ].vm.$emit( 'update:model-value', 'Title' );
		await w.vm.$nextTick();
		expect( ( w.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newUrlValue( [ { url: 'https://example.org', label: 'Title' } ] ) );
	} );
	it( 'accepts legacy string values', () => {
		const w = createTestWrapper( UrlInput, { modelValue: newStringValue( 'https://example.org' ), property: newUrlProperty(), label: 'Source' } );
		expect( w.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( 'https://example.org' );
	} );
	it( 'keeps incomplete input visible and blocks saving without losing the old value', async () => {
		const w = wrapper( newUrlValue( [ 'https://example.org' ] ) );
		w.findComponent( CdxTextInput ).vm.$emit( 'update:model-value', 'https://' );
		await w.vm.$nextTick();
		const api = w.vm as unknown as ValueInputExposes;
		expect( api.unparseableInputMessage!() ).toBeTruthy();
		expect( api.getCurrentValue() ).toEqual( newUrlValue( [ 'https://example.org' ] ) );
		expect( w.findComponent( CdxTextInput ).props( 'modelValue' ) ).toBe( 'https://' );
	} );
	it( 'does not drop additional information if the address is cleared', async () => {
		const w = wrapper( newUrlValue( [ { url: 'https://example.org', label: 'Title' } ] ) );
		w.findComponent( CdxTextInput ).vm.$emit( 'update:model-value', '' );
		await w.vm.$nextTick();
		expect( ( w.vm as unknown as ValueInputExposes ).unparseableInputMessage!() ).toBeTruthy();
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );
	it( 'supports adding and removing complete independent entries', async () => {
		const w = wrapper( newUrlValue( [ { url: 'https://one.org', label: 'One' }, { url: 'https://two.org', label: 'Two' } ] ), true );
		expect( w.findAllComponents( CdxTextInput ) ).toHaveLength( 8 );
		await w.findAllComponents( CdxButton )[ 0 ].trigger( 'click' );
		expect( ( ( w.vm as unknown as ValueInputExposes ).getCurrentValue() as UrlValue ).parts ).toEqual( [ { url: 'https://two.org', label: 'Two' } ] );
		const buttons = w.findAllComponents( CdxButton );
		await buttons[ buttons.length - 1 ].trigger( 'click' );
		expect( w.findAllComponents( CdxTextInput ) ).toHaveLength( 8 );
	} );
	it( 'shows server errors and clears them on editing', async () => {
		const w = createTestWrapper( UrlInput, { modelValue: newUrlValue( [ 'https://example.org' ] ), property: newUrlProperty( { name: 'Source' } ), serverViolations: [ { propertyName: 'Source', code: 'invalid-url', severity: 'error', valuePartIndex: 0, args: [] } ] } );
		expect( w.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		w.findAllComponents( CdxTextInput )[ 1 ].vm.$emit( 'update:model-value', 'Title' );
		await w.vm.$nextTick();
		expect( w.emitted( 'clear-server-violation' ) ).toBeTruthy();
	} );
	it( 'keeps server warning status visible', () => {
		const w = createTestWrapper( UrlInput, { modelValue: newUrlValue( [ 'https://example.org' ] ), property: newUrlProperty( { name: 'Source' } ), serverViolations: [ { propertyName: 'Source', code: 'unique', severity: 'warning', valuePartIndex: null, args: [] } ] } );
		expect( w.findComponent( CdxField ).props( 'status' ) ).toBe( 'warning' );
	} );
	it( 'renders two independent links and escapes HTML in labels', () => {
		const w = createTestWrapper( UrlDisplay, { value: newUrlValue( [ { url: 'https://de.wikipedia.org/wiki/Regiomontanus', label: '<b>Regiomontanus</b>', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia' } ] ), property: newUrlProperty() } );
		const links = w.findAll( 'a' );
		expect( links ).toHaveLength( 2 );
		expect( links[ 0 ].attributes( 'href' ) ).toContain( 'Wikipedia' );
		expect( links[ 1 ].text() ).toBe( '<b>Regiomontanus</b>' );
		expect( w.find( 'b' ).exists() ).toBe( false );
	} );
	it( 'rejects malformed records without silently losing metadata', () => {
		expect( () => newUrlValue( [ { url: 'https://example.org', label: 7 } ] ) ).toThrow();
		expect( () => newUrlValue( [ { url: '', label: 'Lost' } ] ) ).toThrow();
		expect( () => newUrlValue( [ { url: 'https://example.org', other: 'Lost' } ] ) ).toThrow();
	} );
} );
