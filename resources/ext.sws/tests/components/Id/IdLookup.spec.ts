import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, type VueWrapper } from '@vue/test-utils';
import { CdxLookup } from '@wikimedia/codex';
import IdLookup from '@/components/Id/IdLookup.vue';
import { lookupPropertyIds, type IdSuggestion } from '@/persistence/PropertyIdLookup';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';

vi.mock( '@/persistence/PropertyIdLookup', () => ( { lookupPropertyIds: vi.fn() } ) );
const item: IdSuggestion = { id: 'P31', label: 'ist ein(e)', description: 'Klasse', url: 'https://www.wikidata.org/wiki/Property:P31' };
const lookup = vi.mocked( lookupPropertyIds );
describe( 'confirmed source-qualified property selection', () => {
	beforeEach( () => {
		setupMwMock();
		vi.useFakeTimers();
		lookup.mockReset();
		lookup.mockResolvedValue( [ item ] );
	} );
	afterEach( () => {
		vi.useRealTimers();
		vi.restoreAllMocks();
	} );
	function show( id: string | null = null ): VueWrapper<InstanceType<typeof IdLookup>> {
		return createTestWrapper( IdLookup, { source: 'wikidata', modelValue: id } );
	}
	it( 'waits for typing, debounces and commits only a suggested ID', async () => {
		const w = show();
		expect( lookup ).not.toHaveBeenCalled();
		await w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'P31' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		expect( w.vm.unparseableInputMessage() ).not.toBeNull();
		await vi.advanceTimersByTimeAsync( 301 );
		expect( lookup ).toHaveBeenCalledWith( 'wikidata', 'P31', 'search', expect.any( AbortSignal ) );
		expect( w.findComponent( CdxLookup ).props( 'menuItems' )[ 0 ] ).toMatchObject( { label: 'ist ein(e)', supportingText: 'P31' } );
		await w.findComponent( CdxLookup ).vm.$emit( 'update:selected', 'P31' );
		expect( w.emitted( 'update:modelValue' )?.slice( -1 )[ 0 ] ).toEqual( [ 'P31' ] );
		await w.setProps( { modelValue: 'P31' } );
		expect( w.findComponent( CdxLookup ).props( 'inputValue' ) ).toBe( 'ist ein(e)' );
		expect( w.get( 'a' ).attributes( 'title' ) ).toBe( 'P31' );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		w.unmount();
	} );
	it( 'resolves the label of a saved ID without rewriting that ID', async () => {
		const w = show( 'P31' );
		await flushPromises();
		expect( lookup ).toHaveBeenCalledWith( 'wikidata', 'P31', 'resolve', expect.any( AbortSignal ) );
		expect( w.get( 'a' ).text() ).toBe( 'ist ein(e)' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'ignores arbitrary IDs not present in the suggestions', async () => {
		const w = show();
		await w.findComponent( CdxLookup ).vm.$emit( 'update:selected', 'P9999' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'blocks an unconfirmed change while preserving the prior stored reference', async () => {
		const w = show( 'P31' );
		await flushPromises();
		await w.get( 'input' ).setValue( 'other property' );
		expect( w.vm.unparseableInputMessage() ).not.toBeNull();
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'preserves an unchanged reference on an outage, with an explicit warning', async () => {
		lookup.mockRejectedValue( new Error( 'offline' ) );
		const w = show( 'P31' );
		await flushPromises();
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		expect( w.text() ).toContain( 'sws-id-unavailable' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'does not accept a response from a previously selected source', async () => {
		let finish!: ( value: IdSuggestion[] ) => void;
		lookup.mockImplementationOnce( () => new Promise( ( resolve ) => {
			finish = resolve;
		} ) );
		const w = show( 'P31' );
		await w.setProps( { source: 'wikibase-local', modelValue: null } );
		finish( [ item ] );
		await flushPromises();
		expect( w.find( 'a' ).exists() ).toBe( false );
		expect( w.findComponent( CdxLookup ).props( 'inputValue' ) ).toBe( '' );
		w.unmount();
	} );
	it( 'ignores a delayed search after unmounting', async () => {
		const w = show();
		await w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'ist ein' );
		w.unmount();
		await vi.advanceTimersByTimeAsync( 301 );
		expect( lookup ).not.toHaveBeenCalled();
	} );
	it( 'selects a suggestion with the keyboard after typing a label', async () => {
		const w = show();
		const field = w.get( 'input' );
		await field.trigger( 'focus' );
		await field.setValue( 'ist ein' );
		await vi.advanceTimersByTimeAsync( 301 );
		await flushPromises();
		await field.trigger( 'keydown', { key: 'ArrowDown' } );
		await field.trigger( 'keydown', { key: 'Enter' } );
		expect( w.emitted( 'update:modelValue' )?.slice( -1 )[ 0 ] ).toEqual( [ 'P31' ] );
		w.unmount();
	} );
	it( 'cancels a stale search when the original selection is restored', async () => {
		const w = show( 'P31' );
		await flushPromises();
		let finish!: ( value: IdSuggestion[] ) => void;
		lookup.mockImplementationOnce( () => new Promise( ( resolve ) => {
			finish = resolve;
		} ) );
		await w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'other' );
		await vi.advanceTimersByTimeAsync( 301 );
		await w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'ist ein(e)' );
		finish( [ { ...item, id: 'P999' } ] );
		await flushPromises();
		expect( w.findComponent( CdxLookup ).props( 'menuItems' ).map( ( x ) => x.value ) ).not.toContain( 'P999' );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		w.unmount();
	} );

	it( 'never erases a stored ID just because the search input is cleared', async () => {
		const w = show( 'P31' );
		await flushPromises();
		await w.get( 'input' ).setValue( '' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		expect( w.text() ).toContain( 'P31' );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		w.unmount();
	} );

	it( 'retains the saved ID across repeated reopenings with a delayed label lookup', async () => {
		for ( let attempt = 0; attempt < 2; attempt++ ) {
			let finish!: ( value: IdSuggestion[] ) => void;
			lookup.mockImplementationOnce( () => new Promise( ( resolve ) => {
				finish = resolve;
			} ) );
			const w = show( 'P31' );
			await flushPromises();
			expect( w.text() ).toContain( 'P31' );
			finish( [ item ] );
			await flushPromises();
			expect( w.get( 'a' ).text() ).toBe( 'ist ein(e)' );
			expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
			expect( w.vm.unparseableInputMessage() ).toBeNull();
			w.unmount();
		}
	} );

	it( 'keeps a saved ID selected before its label request completes, even if lookup fails', async () => {
		let reject!: ( reason: Error ) => void;
		lookup.mockImplementationOnce( () => new Promise( ( _resolve, fail ) => {
			reject = fail;
		} ) );
		const w = show( 'P31' );
		await flushPromises();
		expect( w.findComponent( CdxLookup ).props( 'selected' ) ).toBe( 'P31' );
		expect( w.findComponent( CdxLookup ).props( 'menuItems' )[ 0 ] ).toMatchObject( { value: 'P31', label: 'P31' } );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		reject( new Error( 'offline' ) );
		await flushPromises();
		expect( w.findComponent( CdxLookup ).props( 'selected' ) ).toBe( 'P31' );
		expect( w.find( 'a' ).exists() ).toBe( false );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );

	for ( const config of [
		{ source: 'wikidata', id: 'P31', label: 'ist ein(e)', url: 'https://www.wikidata.org/wiki/Property:P31' },
		{ source: 'wikibase-local', id: 'P12', label: 'Geburt', url: window.location.origin + '/wiki/Property:P12' },
	] as const ) {
		it( `searches again when the saved ID is cleared and retyped (${ config.source })`, async () => {
			lookup.mockResolvedValue( [ { id: config.id, label: config.label, description: '', url: config.url } ] );
			const w = createTestWrapper( IdLookup, { source: config.source, modelValue: config.id } );
			await flushPromises();
			await w.get( 'input' ).setValue( '' );
			await w.get( 'input' ).setValue( config.id );
			await vi.advanceTimersByTimeAsync( 301 );
			expect( lookup ).toHaveBeenLastCalledWith( config.source, config.id, 'search', expect.any( AbortSignal ) );
			expect( w.findComponent( CdxLookup ).props( 'menuItems' ).map( ( row ) => row.value ) ).toContain( config.id );
			expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
			w.unmount();
		} );
	}

} );
