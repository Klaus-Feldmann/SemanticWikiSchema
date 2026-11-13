import { readFileSync } from 'node:fs';
import * as Vue from 'vue';
import * as Codex from '@wikimedia/codex';
import { mount, flushPromises } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
const create = new Function( 'module', readFileSync( process.cwd() + '/../prefixSearch/component.js', 'utf8' ) + '\nreturn module.exports;' )( { exports: {} } );
const Select = Vue.defineComponent( { props: [ 'selected', 'menuItems' ], emits: [ 'update:selected' ], render: () => Vue.h( 'select' ) } );
const Input = Vue.defineComponent( { props: [ 'searchResults', 'searchFooterUrl' ], emits: [ 'input' ], render() { return Vue.h( 'form', this.$slots.default?.() ); } } );
let w: ReturnType<typeof mount>;
beforeEach( () => { vi.useFakeTimers(); sessionStorage.clear(); vi.stubGlobal( 'mw', { msg: ( s: string ) => s, notify: vi.fn(), util: { getUrl: ( s: string ) => '/wiki/' + s }, config: { get: () => '/w/index.php' } } ); } );
afterEach( () => { w?.unmount(); vi.useRealTimers(); vi.unstubAllGlobals(); } );
function setup( search: ReturnType<typeof vi.fn> ) {
	w = mount( create( Vue, { CdxSelect: Select, CdxTypeaheadSearch: Input }, search ), { props: { scopes: [ { id: 0, label: 'Main' }, { id: 14, label: 'Kategorie' } ] } } );
	return { input: w.findComponent( Input ), select: w.findComponent( Select ) };
}
describe( 'Global PrefixSearch', () => {
	it( 'keeps the term across scope changes and scopes the full search', async () => {
		const search = vi.fn().mockResolvedValue( [] ); const { input, select } = setup( search );
		input.vm.$emit( 'input', 'Sprache' ); await vi.advanceTimersByTimeAsync( 200 );
		expect( search ).toHaveBeenLastCalledWith( 'Sprache', 10, 0 );
		select.vm.$emit( 'update:selected', 14 ); await vi.advanceTimersByTimeAsync( 200 );
		expect( search ).toHaveBeenLastCalledWith( 'Sprache', 10, 14 );
		expect( w.find( 'input[name="ns14"]' ).exists() ).toBe( true );
		expect( input.props( 'searchFooterUrl' ) ).toContain( 'ns14=1' );
	} );
	it( 'discards stale responses', async () => {
		let finish: ( x: unknown ) => void = () => {};
		const search = vi.fn().mockImplementationOnce( () => new Promise( r => { finish = r; } ) ).mockResolvedValue( [] );
		const { input, select } = setup( search ); input.vm.$emit( 'input', 'x' ); await vi.advanceTimersByTimeAsync( 200 );
		select.vm.$emit( 'update:selected', 14 ); finish( [ { title: 'Old result' } ] ); await flushPromises();
		expect( input.props( 'searchResults' ) ).toEqual( [] );
	} );
	it( 'labels DISPLAYTITLE while opening the canonical page', async () => {
		const { input } = setup( vi.fn().mockResolvedValue( [ { title: 'Canonical', displayTitle: 'Anzeige' } ] ) );
		input.vm.$emit( 'input', 'x' ); await vi.advanceTimersByTimeAsync( 200 );
		expect( input.props( 'searchResults' )[ 0 ] ).toMatchObject( { label: 'Anzeige', url: '/wiki/Canonical' } );
	} );
} );

describe( 'Real Codex integration', () => {
	it( 'renders the search form, scoped fields and direct suggestion links', async () => {
		vi.stubGlobal( 'ResizeObserver', class { observe() {} unobserve() {} disconnect() {} } );
		const search = vi.fn().mockResolvedValue( [ { title: 'Kategorie:Sprache', displayTitle: 'Sprachen', description: 'Untertitel', thumbnail: { url: 'https://example.org/image.jpg' }, redirectFrom: 'Sprache' } ] );
		w = mount( create( Vue, Codex, search ), { props: { scopes: [ { id: 14, label: 'Kategorie' } ] } } );
		expect( w.find( 'form' ).attributes( 'action' ) ).toBe( '/w/index.php' );
		expect( w.find( 'input[name="ns14"]' ).exists() ).toBe( true );
		await w.find( 'input[type="search"]' ).trigger( 'focus' );
		await w.find( 'input[type="search"]' ).setValue( 'Sprache' );
		await vi.advanceTimersByTimeAsync( 1000 );
		await flushPromises();
		await Vue.nextTick();
		expect( search ).toHaveBeenCalledWith( 'Sprache', 10, 14 );
		expect( w.findComponent( Codex.CdxTypeaheadSearch ).props( 'visibleItemLimit' ) ).toBe( 10 );
		expect( w.findComponent( Codex.CdxTypeaheadSearch ).props( 'showThumbnail' ) ).toBe( true );
		expect( w.findComponent( Codex.CdxThumbnail ).props( 'thumbnail' ) ).toEqual( { url: 'https://example.org/image.jpg' } );
		expect( w.text() ).toContain( 'Untertitel' );
		expect( w.findComponent( Codex.CdxTypeaheadSearch ).props( 'searchResults' )[ 0 ] ).toMatchObject( { label: 'Sprachen', url: '/wiki/Kategorie:Sprache', description: 'Untertitel', thumbnail: { url: 'https://example.org/image.jpg' }, match: 'sws-prefixsearch-redirect' } );
	} );
} );
