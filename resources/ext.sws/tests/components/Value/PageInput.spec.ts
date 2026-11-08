/* eslint-disable @typescript-eslint/explicit-function-return-type, default-param-last */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { CdxLookup, CdxButton } from '@wikimedia/codex';
import PageInput from '@/components/Value/PageInput.vue';
import PageAttributesEditor from '@/components/SchemaEditor/Property/PageAttributesEditor.vue';
import { CdxSelect } from '@wikimedia/codex';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import PageDisplay from '@/components/Value/PageDisplay.vue';
import LocalPageInput from '@/components/common/LocalPageInput.vue';
import { newStringValue } from '@/domain/Value';
import { newPageProperty } from '@/domain/propertyTypes/Page';
import { createTestWrapper } from '../../VueTestHelpers';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
import { pageTitleInput, lookupLocalPages, localPageInfo } from '@/presentation/localPages';
import { Neo } from '@/Neo';
import { SWSServices } from '@/SWSServices';

beforeEach( () => {
	vi.stubGlobal( 'mw', {
		msg: ( key: string ) => key, message: ( key: string ) => ( { text: () => key } ),
		config: { get: () => ( { 0: '', 102: 'Werk' } ) },
		Title: { newFromText: ( text: string, ns = 0 ) => {
			if ( !text.trim() || /[[\]{}|<>]/.test( text ) ) {
				return null;
			}
			const full = ( ns === 102 && !text.includes( ':' ) ? 'Werk:' : '' ) + text.trim().split( '_' ).join( ' ' );
			return { getPrefixedText: () => full, getNamespaceId: () => full.startsWith( 'Special:' ) ? -1 : 0 };
		} },
		util: { wikiScript: () => '/w/api.php', getUrl: ( title: string, params?: Record<string, string> ) =>
			'/wiki/' + encodeURIComponent( title ) + ( params ? '?' + new URLSearchParams( params ).toString() : '' ) },
	} );
	vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { query: { pages: [ { title: 'Athen', ns: 0, pageid: 1 }, { title: 'Rom', ns: 0, missing: true } ] } } ) } ) );
} );
afterEach( () => {
	vi.unstubAllGlobals();
	vi.restoreAllMocks();
	vi.useRealTimers();
} );
function editor( value = newStringValue(), multiple = false, pageNamespace?: number ) {
	return createTestWrapper( PageInput, { modelValue: value, label: 'Ort', property: newPageProperty( { name: 'Ort', multiple, pageNamespace } ) } );
}
const exposed = ( wrapper: ReturnType<typeof editor> ) => wrapper.vm as unknown as ValueInputExposes;
describe( 'Local page field', () => {
	it( 'is a registered string-backed type and reopens without loss', () => {
		expect( Neo.getInstance().getPropertyTypeRegistry().getType( 'page' ).getValueType() ).toBe( 'string' );
		const w = editor( newStringValue( 'Werk:Abhandlung' ) );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Werk:Abhandlung' ) );
		expect( w.findComponent( CdxLookup ).props( 'inputValue' ) ).toBe( 'Werk:Abhandlung' );
	} );
	it( 'applies a namespace preference without rewriting the text during typing', async () => {
		const w = editor( newStringValue(), false, 102 );
		w.findComponent( LocalPageInput ).vm.$emit( 'update:modelValue', 'De mathematica perfectione' );
		await w.vm.$nextTick();
		expect( w.findComponent( LocalPageInput ).props( 'modelValue' ) ).toBe( 'De mathematica perfectione' );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Werk:De mathematica perfectione' ) );
	} );
	it( 'retains an explicit namespace', () => {
		expect( pageTitleInput( 'Vermerk:Quelle', 102 ) ).toBe( 'Vermerk:Quelle' );
	} );
	it.each( [ 'https://example.org', 'Athen#Antike', 'bad[title', 'Special:RecentChanges' ] )( 'blocks invalid or non-page input %s', async ( text ) => {
		const w = editor();
		w.findComponent( LocalPageInput ).vm.$emit( 'update:modelValue', text );
		await w.vm.$nextTick();
		expect( exposed( w ).unparseableInputMessage!() ).toBeTruthy();
	} );
	it( 'adds and removes separate page values', async () => {
		const w = editor( newStringValue( 'Athen', 'Rom' ), true );
		await w.findAllComponents( CdxButton )[ 0 ].trigger( 'click' );
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Rom' ) );
		const buttons = w.findAllComponents( CdxButton );
		await buttons[ buttons.length - 1 ].trigger( 'click' );
		expect( w.findAllComponents( LocalPageInput ) ).toHaveLength( 2 );
	} );
	it( 'uses local title links and batches existence requests', async () => {
		const w = createTestWrapper( PageDisplay, { value: newStringValue( 'Athen', 'Rom' ), property: newPageProperty() } );
		await flushPromises();
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( w.findAll( 'a' ).map( ( link ) => link.text() ) ).toEqual( [ 'Athen', 'Rom' ] );
		expect( w.find( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Athen' );
	} );
	it( 'renders a missing page as a red edit link', async () => {
		vi.mocked( fetch ).mockResolvedValue( { ok: true, json: async () => ( { query: { pages: [ { title: 'Neue Seite', ns: 0, missing: true } ] } } ) } as Response );
		const w = createTestWrapper( PageDisplay, { value: newStringValue( 'Neue Seite' ), property: newPageProperty() } );
		await flushPromises();
		expect( w.find( 'a' ).classes() ).toContain( 'new' );
		expect( w.find( 'a' ).attributes( 'href' ) ).toContain( 'action=edit&redlink=1' );
	} );
	it( 'does not turn a lookup failure into a missing-page result', async () => {
		vi.mocked( fetch ).mockRejectedValue( new Error( 'offline' ) );
		await expect( lookupLocalPages( [ 'Athen' ] ) ).rejects.toThrow();
		const w = createTestWrapper( PageDisplay, { value: newStringValue( 'Athen' ), property: newPageProperty() } );
		await flushPromises();
		expect( w.find( 'a' ).classes() ).not.toContain( 'new' );
	} );
	it( 'uses normalized API titles, and identifies interwiki results as invalid', async () => {
		vi.mocked( fetch ).mockResolvedValue( { ok: true, json: async () => ( { query: { normalized: [ { from: 'Athen_Stadt', to: 'Athen Stadt' } ], pages: [ { title: 'Athen Stadt', ns: 0, pageid: 2 } ], interwiki: [ { title: 'w:Foo' } ] } } ) } as Response );
		expect( await lookupLocalPages( [ 'Athen_Stadt', 'w:Foo' ] ) ).toEqual( [
			{ title: 'Athen Stadt', exists: true, valid: true }, { title: 'w:Foo', exists: false, valid: false },
		] );
		expect( vi.mocked( fetch ).mock.calls[ 0 ][ 1 ] ).toEqual( { credentials: 'same-origin' } );
	} );
	it( 'deduplicates the same title in the rendering batch', async () => {
		const [ a, b ] = await Promise.all( [ localPageInfo( 'Athen' ), localPageInfo( 'Athen' ) ] );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		expect( a ).toEqual( b );
	} );
} );

describe( 'Page schema and search behavior', () => {
	it( 'preserves defaults, namespace and text settings through a schema save/reopen', () => {
		const input = { description: '', propertyDefinitions: { Bezug: { type: 'page', description: '', required: true,
			multiple: true, uniqueItems: true, minLength: 2, maxLength: 150, pageNamespace: 102, default: [ 'Werk:Abhandlung' ] } } };
		const schema = new SchemaDeserializer().deserialize( 'Person', input );
		const saved = new SchemaSerializer().schemaToJson( schema );
		const reopened = new SchemaDeserializer().deserialize( 'Person', saved );
		const property = reopened.getPropertyDefinition( 'Bezug' );
		expect( property ).toMatchObject( { type: 'page', required: true, multiple: true, uniqueItems: true,
			pageNamespace: 102, minLength: 2, maxLength: 150, default: newStringValue( 'Werk:Abhandlung' ) } );
	} );
	it( 'sets and clears the numeric namespace preference', async () => {
		const w = createTestWrapper( PageAttributesEditor, { property: newPageProperty() } );
		w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 102 );
		await w.vm.$nextTick();
		expect( w.emitted( 'update:property' )![ 0 ] ).toEqual( [ { pageNamespace: 102 } ] );
		w.findComponent( CdxSelect ).vm.$emit( 'update:selected', '' );
		await w.vm.$nextTick();
		expect( w.emitted( 'update:property' )![ 1 ] ).toEqual( [ { pageNamespace: undefined } ] );
	} );
	it( 'shows a lookup failure without claiming that the page is missing', async () => {
		vi.mocked( fetch ).mockRejectedValue( new Error( 'offline' ) );
		const w = editor();
		w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'Athen' );
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 230 );
		} );
		await flushPromises();
		expect( w.text() ).toContain( 'sws-page-search-failed' );
		expect( w.text() ).not.toContain( 'sws-page-missing' );
	} );
	it( 'allows a missing local page and explains the missing state', async () => {
		vi.mocked( fetch ).mockResolvedValue( { ok: true, json: async () => ( { query: { pages: [ { title: 'Neue Seite', ns: 0, missing: true } ] } } ) } as Response );
		const w = editor();
		w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'Neue Seite' );
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 230 );
		} );
		await flushPromises();
		expect( w.text() ).toContain( 'sws-page-missing' );
		expect( exposed( w ).unparseableInputMessage!() ).toBeNull();
		expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Neue Seite' ) );
	} );
	it( 'accepts a selected title and keeps it after reopening', async () => {
		vi.spyOn( SWSServices, 'getPageTitleSearch' ).mockReturnValue( {
			searchPageTitles: async () => [ { title: 'Werk:Abhandlung', pageId: 42 } ],
		} );
		const w = editor();
		w.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'Abhand' );
		await new Promise( ( resolve ) => {
			setTimeout( resolve, 230 );
		} );
		await flushPromises();
		w.findComponent( CdxLookup ).vm.$emit( 'update:selected', 'Werk:Abhandlung' );
		await w.vm.$nextTick();
		const value = exposed( w ).getCurrentValue();
		expect( value ).toEqual( newStringValue( 'Werk:Abhandlung' ) );
		const reopened = editor( value as ReturnType<typeof newStringValue> );
		expect( exposed( reopened ).getCurrentValue() ).toEqual( value );
	} );
} );

it( 'keeps stored main-namespace titles when a namespace preference is changed', async () => {
	const w = editor( newStringValue( 'Athen', 'Rom' ), true, 102 );
	expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Athen', 'Rom' ) );
	w.findAllComponents( LocalPageInput )[ 1 ].vm.$emit( 'update:modelValue', 'Abhandlung' );
	await w.vm.$nextTick();
	expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Athen', 'Werk:Abhandlung' ) );
} );
it( 'a selected main-namespace title overrides the namespace preference', async () => {
	const w = editor( newStringValue(), false, 102 );
	w.findComponent( LocalPageInput ).vm.$emit( 'update:modelValue', 'Athen', true );
	await w.vm.$nextTick();
	expect( exposed( w ).getCurrentValue() ).toEqual( newStringValue( 'Athen' ) );
} );
