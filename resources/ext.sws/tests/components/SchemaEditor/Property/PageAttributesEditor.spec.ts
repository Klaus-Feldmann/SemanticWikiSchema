import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxCheckbox, CdxSelect } from '@wikimedia/codex';
import PageAttributesEditor from '@/components/SchemaEditor/Property/PageAttributesEditor.vue';
import { newPageProperty, PageType } from '@/domain/propertyTypes/Page';
import { PropertyName } from '@/domain/PropertyDefinition';
import { createTestWrapper, setupMwMock } from '../../../VueTestHelpers';

describe( 'PageAttributesEditor category assignments', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'message' ], messages: { article: 'Inhalt' } } );
		( mw.config.get as any ).mockImplementation( ( key: string ) => ( {
			wgFormattedNamespaces: { 0: '', 14: 'Kategorie', 10: 'Vorlage' },
			wgSWSPrefixSearchNamespaces: [ { id: 0, label: 'Artikel' }, { id: 14, label: 'Kategorie' } ],
		} as any )[ key ] );
	} );

	it( 'offers assignment only for the fixed category namespace', () => {
		for ( const ns of [ undefined, 0, 10, 14 ] ) {
			const wrapper = createTestWrapper( PageAttributesEditor, { property: newPageProperty( { pageNamespace: ns } ) } );
			expect( wrapper.text().includes( 'sws-page-assign-categories' ) ).toBe( ns === 14 );
		}
	} );

	it( 'emits explicit opt-in and resets it on namespace change', async () => {
		const wrapper = createTestWrapper( PageAttributesEditor, { property: newPageProperty( { pageNamespace: 14 } ) } );
		await wrapper.findAllComponents( CdxCheckbox )[ 0 ].vm.$emit( 'update:modelValue', true );
		expect( wrapper.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { assignCategories: true } ] );
		await wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 0 );
		expect( wrapper.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { pageNamespace: 0, assignCategories: false } ] );
	} );

	it( 'uses configured labels, preserving numeric namespace identity', () => {
		const wrapper = createTestWrapper( PageAttributesEditor, { property: newPageProperty() } );
		expect( wrapper.findComponent( CdxSelect ).props( 'menuItems' ) ).toContainEqual( { value: 0, label: 'Artikel' } );
	} );

	it( 'uses the article system message for namespace zero even when not offered globally', () => {
		( mw.config.get as any ).mockImplementation( ( key: string ) => ( {
			wgFormattedNamespaces: { 0: '', 14: 'Kategorie' },
			wgSWSPrefixSearchNamespaces: [ { id: 14, label: 'Kategorie' } ],
		} as any )[ key ] );
		const wrapper = createTestWrapper( PageAttributesEditor, { property: newPageProperty() } );
		expect( wrapper.findComponent( CdxSelect ).props( 'menuItems' ) ).toContainEqual( { value: 0, label: 'Inhalt' } );
	} );

	it( 'retains the category flag when reading stored definitions', () => {
		const property = new PageType().createPropertyDefinitionFromJson( {
			name: new PropertyName( 'Kategorien' ), type: 'page', description: '', required: false,
		}, { pageNamespace: 14, assignCategories: true, multiple: true } );
		expect( property.assignCategories ).toBe( true );
		expect( property.multiple ).toBe( true );
		expect( newPageProperty().assignCategories ).toBe( false );
	} );
} );
