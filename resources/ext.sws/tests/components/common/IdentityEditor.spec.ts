import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import IdentityEditor from '@/components/common/IdentityEditor.vue';

const model = { identityLanguage: 'de', labels: { de: 'Vorlage' }, descriptions: { de: 'Zum Kennzeichnen innerer Zitate.' } };
const create = (): ReturnType<typeof mount> => mount( IdentityEditor, { props: { modelValue: model }, global: { mocks: { $i18n: ( key: string ) => ( { text: () => key } ) } } } );

describe( 'IdentityEditor descriptions', () => {
	it( 'shows existing descriptions in a paragraph-sized editor without changing their values', () => {
		const wrapper = create();
		expect( wrapper.get( 'textarea' ).element.value ).toBe( model.descriptions.de );
		expect( wrapper.get( 'textarea' ).attributes( 'rows' ) ).toBe( '3' );
		expect( wrapper.findAll( 'input' )[ 0 ].element.value ).toBe( 'Vorlage' );
	} );
	it( 'keeps the existing metadata shape, labels and language when editing a description', async () => {
		const wrapper = create();
		await wrapper.get( 'textarea' ).setValue( 'Ein Absatz.\nEine weitere Zeile.' );
		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ { ...model, descriptions: { de: 'Ein Absatz.\nEine weitere Zeile.' } } ] );
	} );
	it( 'removes a cleared description instead of leaving an empty metadata value', async () => {
		const wrapper = create();
		await wrapper.get( 'textarea' ).setValue( '' );
		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ { ...model, descriptions: {} } ] );
	} );
} );
