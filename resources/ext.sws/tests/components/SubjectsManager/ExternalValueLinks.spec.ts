import { beforeEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import ExternalStatementDisplay from '@/components/SubjectsManager/ExternalStatementDisplay.vue';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import type { ExternalField } from '@/composables/useExternalStatements';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';
const field: ExternalField = { name: 'Klassifizierung', source: 'wikidata', propertyId: 'P31',
	propertyLabel: 'ist ein(e)', propertyUrl: 'https://www.wikidata.org/wiki/Property:P31', status: 'resolved',
	values: [ { kind: 'entity', id: 'Q5', label: 'Mensch', url: 'https://www.wikidata.org/wiki/Q5' } ] };
function show( wikiLink?: PropertyDefinition['wikiLink'], data: ExternalField = structuredClone( field ) ): VueWrapper {
	return mount( ExternalStatementDisplay, { props: { field: data, property: { ...newTextProperty(), wikiLink } },
		global: { mocks: { $i18n: createI18nMock() } } } );
}
describe( 'single-line derived value links', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'util' ] } );
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
	} );
	it( 'keeps source links by default and renders inline children without forced breaks', () => {
		const w = show();
		expect( w.findAll( 'div' ) ).toHaveLength( 1 );
		expect( w.findAll( 'br' ) ).toHaveLength( 0 );
		expect( w.text().replace( /\s+/g, ' ' ) ).toBe( 'Wikidata: ist ein(e) Mensch' );
		expect( w.findAll( 'a' ).map( ( a ) => a.attributes( 'href' ) ) ).toEqual( [ field.propertyUrl, field.values[ 0 ].url ] );
		expect( w.attributes( 'style' ) || '' ).not.toContain( 'nowrap' );
		w.unmount();
	} );
	it( 'links only the value to the local title without modifying the source data', () => {
		const data = structuredClone( field );
		const before = JSON.stringify( data );
		const w = show( { externalTarget: 'local' }, data );
		expect( w.findAll( 'a' ).map( ( a ) => a.attributes( 'href' ) ) ).toEqual( [ field.propertyUrl, '/wiki/Mensch' ] );
		expect( JSON.stringify( data ) ).toBe( before );
		w.unmount();
	} );
	it( 'uses the configured namespace for local navigation', () => {
		const w = show( { externalTarget: 'local', namespace: 'Werk' } );
		expect( w.findAll( 'a' )[ 1 ].attributes( 'href' ) ).toBe( '/wiki/Werk%3AMensch' );
		w.unmount();
	} );
	it( 'does not require a local page to already exist', () => {
		const data = structuredClone( field );
		data.values[ 0 ].label = 'Noch nicht angelegte Seite';
		const w = show( { externalTarget: 'local' }, data );
		expect( w.findAll( 'a' )[ 1 ].attributes( 'href' ) ).toBe( '/wiki/Noch%20nicht%20angelegte%20Seite' );
		w.unmount();
	} );
	it( 'retains the source target when the only label is the source ID', () => {
		const data = structuredClone( field );
		data.values[ 0 ].label = 'Q5';
		const w = show( { externalTarget: 'local' }, data );
		expect( w.findAll( 'a' )[ 1 ].attributes( 'href' ) ).toBe( field.values[ 0 ].url );
		w.unmount();
	} );
	it( 'retains the source target for an invalid local title', () => {
		const data = structuredClone( field );
		data.values[ 0 ].label = '<bad title>';
		const w = show( { externalTarget: 'local' }, data );
		expect( w.findAll( 'a' )[ 1 ].attributes( 'href' ) ).toBe( field.values[ 0 ].url );
		w.unmount();
	} );
	it( 'keeps local repository property and value IDs source-qualified by default', () => {
		const data: ExternalField = { ...field, source: 'wikibase-local', propertyUrl: '/wiki/Property:P31',
			values: [ { kind: 'entity', id: 'Q5', label: 'Lokaler Wert', url: '/wiki/Item:Q5' } ] };
		const w = show( undefined, data );
		expect( w.text() ).toContain( 'Wikibase:' );
		expect( w.findAll( 'a' ).map( ( a ) => a.attributes( 'href' ) ) ).toEqual( [ '/wiki/Property:P31', '/wiki/Item:Q5' ] );
		w.unmount();
	} );
	it( 'retains multiple values and separates them inline', () => {
		const data = structuredClone( field );
		data.values.push( { kind: 'entity', id: 'Q6', label: 'Zweite Klasse', url: 'https://www.wikidata.org/wiki/Q6' } );
		const w = show( { externalTarget: 'local' }, data );
		expect( w.text().replace( /\s+/g, ' ' ) ).toBe( 'Wikidata: ist ein(e) Mensch; Zweite Klasse' );
		expect( w.findAll( 'a' ) ).toHaveLength( 3 );
		w.unmount();
	} );
} );
