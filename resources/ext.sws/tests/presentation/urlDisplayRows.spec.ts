import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { expandUrlDisplayRows } from '@/presentation/urlDisplayRows';
import { newUrlValue, newStringValue, type UrlValue } from '@/domain/Value';
import { newUrlProperty, type UrlProperty } from '@/domain/propertyTypes/Url';
import PropertyLabel from '@/components/common/PropertyLabel.vue';
import UrlDisplay from '@/components/Value/UrlDisplay.vue';
const row = ( parts: unknown ): { propertyDefinition: UrlProperty; value: UrlValue } => ( { propertyDefinition: newUrlProperty( { name: 'Quellenangabe' } ), value: newUrlValue( parts ) } );
beforeEach( () => vi.stubGlobal( 'mw', { util: { getUrl: ( title: string ) => '/wiki/' + encodeURIComponent( title ) } } ) );
afterEach( () => vi.unstubAllGlobals() );
describe( 'URL caption as left-hand property label', () => {
	it( 'renders the caption link on the left and only the article on the right', () => {
		const rows = expandUrlDisplayRows( [ row( [ { url: 'https://de.wikipedia.org/wiki/Regiomontanus', label: 'Regiomontanus', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia' } ] ) ] );
		const host = defineComponent( { components: { PropertyLabel, UrlDisplay }, setup: () => ( { rows } ), template: '<dl><div v-for="(row,i) in rows" :key="i"><dt><PropertyLabel :property="row.propertyDefinition" /></dt><dd><UrlDisplay :property="row.propertyDefinition" :value="row.value" /></dd></div></dl>' } );
		const w = mount( host );
		expect( w.get( 'dt' ).text() ).toBe( 'Wikipedia (de)' );
		expect( w.get( 'dt a' ).attributes( 'href' ) ).toBe( '/wiki/Vermerk%3AWikipedia' );
		expect( w.get( 'dd' ).text() ).toBe( 'Regiomontanus' );
		expect( w.findAll( 'dd a' ) ).toHaveLength( 1 );
		expect( w.get( 'dd a' ).attributes( 'href' ) ).toBe( 'https://de.wikipedia.org/wiki/Regiomontanus' );
	} );
	it( 'uses one correctly paired row per source without mutating the field or values', () => {
		const source = row( [ { url: 'https://one.org', caption: 'One', label: 'First' }, { url: 'https://two.org', caption: 'Two', label: 'Second' } ] );
		const before = JSON.stringify( source );
		const rows = expandUrlDisplayRows( [ source ] );
		expect( rows.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'One', 'Two' ] );
		expect( rows.map( ( r ) => r.value.parts[ 0 ] ) ).toEqual( [ { url: 'https://one.org', caption: 'One', label: 'First' }, { url: 'https://two.org', caption: 'Two', label: 'Second' } ] );
		expect( new Set( rows.map( ( r ) => r.sourcePropertyName ) ).size ).toBe( 1 );
		expect( JSON.stringify( source ) ).toBe( before );
	} );
	it( 'keeps the schema label and link when the caption is empty', () => {
		const source = row( [ { url: 'https://example.org', label: 'Title', caption: '  ' } ] );
		const [ result ] = expandUrlDisplayRows( [ source ] );
		expect( result.propertyDefinition.name.toString() ).toBe( 'Quellenangabe' );
		expect( result.propertyDefinition.wikiLink ).toEqual( source.propertyDefinition.wikiLink );
	} );
	it( 'does not invent an internal link when a caption has no local target', () => {
		const [ result ] = expandUrlDisplayRows( [ row( [ { url: 'https://example.org', caption: 'Source' } ] ) ] );
		expect( result.propertyDefinition.wikiLink?.label ).toBe( false );
	} );
	it( 'keeps legacy URL values readable', () => {
		const rows = expandUrlDisplayRows( [ { propertyDefinition: newUrlProperty( { name: 'Source' } ), value: newStringValue( 'https://example.org' ) } ] );
		expect( rows[ 0 ].propertyDefinition.name.toString() ).toBe( 'Source' );
	} );
} );
