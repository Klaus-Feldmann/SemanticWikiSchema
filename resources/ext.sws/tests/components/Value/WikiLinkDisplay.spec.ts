import { mount } from '@vue/test-utils';
import { it, expect, vi } from 'vitest';
import TextDisplay from '@/components/Value/TextDisplay.vue';
import PropertyLabel from '@/components/common/PropertyLabel.vue';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

vi.stubGlobal( 'mw', { util: { getUrl: ( title: string ) => '/wiki/' + encodeURIComponent( title ) } } );
it( 'keeps value links after an arbitrary rename and links the new label', async () => {
	const property = { ...newTextProperty(), wikiLink: { namespace: 'Werk' } };
	const value = mount( TextDisplay, { props: { property, value: newStringValue( 'Titel' ) } } );
	const label = mount( PropertyLabel, { props: { property } } );
	const renamed = { ...property, name: new PropertyName( 'Beliebig umbenannt' ) };
	await value.setProps( { property: renamed } );
	await label.setProps( { property: renamed } );
	expect( value.find( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Werk%3ATitel' );
	expect( label.find( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Beliebig%20umbenannt' );
} );
it( 'does not interpret data as HTML', () => {
	const wrapper = mount( TextDisplay, { props: { property: newTextProperty(), value: newStringValue( '<img src=x onerror=alert(1)>' ) } } );
	expect( wrapper.find( 'img' ).exists() ).toBe( false );
	expect( wrapper.find( 'a' ).exists() ).toBe( false );
} );
