import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { CdxSelect } from '@wikimedia/codex';
import ContentWidthSelect from '@/components/common/ContentWidthSelect.vue';

describe( 'ContentWidthSelect', () => {
	it( 'measures every choice without exposing measurement text to assistive technology', async () => {
		const wrapper = mount( ContentWidthSelect, { props: {
			selected: 0,
			menuItems: [ { value: 0, label: 'Artikel' }, { value: 14, label: 'Kategorie' } ],
		} } );
		const measure = wrapper.get( '.ext-sws-content-width-select__measure' );
		expect( measure.attributes( 'aria-hidden' ) ).toBe( 'true' );
		expect( measure.text() ).toContain( 'Kategorie' );
		expect( wrapper.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 0 );
		await wrapper.setProps( { menuItems: [ { value: 0, label: 'Inhaltsseiten' } ] } );
		expect( measure.text() ).toContain( 'Inhaltsseiten' );
		expect( measure.text() ).not.toContain( 'Kategorie' );
	} );

	it( 'forwards selection events, accessibility labels and disabled state', async () => {
		const update = vi.fn();
		const wrapper = mount( ContentWidthSelect, {
			props: { menuItems: [ { value: 14, label: 'Kategorie' } ], selected: null },
			attrs: { 'aria-label': 'Namensraum', disabled: true, 'onUpdate:selected': update },
		} );
		const select = wrapper.findComponent( CdxSelect );
		expect( select.props( 'disabled' ) ).toBe( true );
		expect( select.find( '[role="combobox"]' ).attributes( 'aria-label' ) ).toBe( 'Namensraum' );
		await select.vm.$emit( 'update:selected', 14 );
		expect( update ).toHaveBeenCalledWith( 14 );
	} );

	it( 'includes the empty-selection label and preserves custom selected content', () => {
		const wrapper = mount( ContentWidthSelect, {
			props: { menuItems: [ { value: 'ce', label: 'n. Chr.' } ], selected: 'ce' },
			attrs: { 'default-label': 'Bitte auswählen' },
			slots: { label: () => 'Eigene Beschriftung' },
		} );
		expect( wrapper.get( '.ext-sws-content-width-select__measure' ).text() ).toContain( 'Bitte auswählen' );
		expect( wrapper.findComponent( CdxSelect ).text() ).toContain( 'Eigene Beschriftung' );
	} );
} );
