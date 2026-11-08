import { mount } from '@vue/test-utils';
import { describe, it, expect, vi } from 'vitest';
import { nextTick } from 'vue';
import { CdxTextInput, CdxSelect } from '@wikimedia/codex';
import HistoricalDateInput from '@/components/Value/HistoricalDateInput.vue';
import { newStringValue } from '@/domain/Value';
import { newTextProperty } from '@/domain/propertyTypes/Text';

// Integration tests for the normal SWS frontend suite; not a substitute for live MediaWiki.
describe( 'historical date editing', () => {
	vi.stubGlobal( 'mw', { msg: ( key: string ) => key, message: ( key: string ) => ( { text: () => key } ) } );
	function input( raw: string ): ReturnType<typeof mount> {
		return mount( HistoricalDateInput, { props: {
			property: { ...newTextProperty(), type: 'historicalDate' }, modelValue: newStringValue( raw ),
		}, global: { mocks: { $i18n: ( key: string ) => ( { text: () => key } ) } } } );
	}
	it( 'opens BCE without inventing a month/day and preserves it when saved', () => {
		const wrapper = input( '-0044' );
		const exposed = wrapper.vm as unknown as { getCurrentValue(): { parts: string[] } };
		expect( exposed.getCurrentValue().parts ).toEqual( [ '-0044' ] );
		expect( wrapper.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'bce' );
	} );
	it( 'blocks an impossible calendar date instead of silently clearing it', async () => {
		const wrapper = input( '2024-02-29' );
		wrapper.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:modelValue', '2025' );
		await nextTick();
		const exposed = wrapper.vm as unknown as { unparseableInputMessage(): string | null };
		expect( exposed.unparseableInputMessage() ).not.toBeNull();
	} );
	it( 'keeps a year-month value at month precision when reopened', () => {
		const wrapper = input( '1458-10' );
		const exposed = wrapper.vm as unknown as { getCurrentValue(): { parts: string[] } };
		expect( exposed.getCurrentValue().parts ).toEqual( [ '1458-10' ] );
	} );
	it( 'switches the era without inventing a month or day', async () => {
		const wrapper = input( '0044' );
		wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'bce' );
		await nextTick();
		const exposed = wrapper.vm as unknown as { getCurrentValue(): { parts: string[] } };
		expect( exposed.getCurrentValue().parts ).toEqual( [ '-0044' ] );
	} );
	it( 'retains invalid year-zero input when the parent echoes an empty value', async () => {
		const wrapper = input( '1458' );
		wrapper.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:modelValue', '0' );
		await wrapper.setProps( { modelValue: undefined } );
		const exposed = wrapper.vm as unknown as { unparseableInputMessage(): string | null };
		expect( exposed.unparseableInputMessage() ).not.toBeNull();
		expect( wrapper.findAllComponents( CdxTextInput )[ 0 ].props( 'modelValue' ) ).toBe( '0' );
	} );
	it( 'blocks a day entered without a month', async () => {
		const wrapper = input( '1458' );
		wrapper.findAllComponents( CdxTextInput )[ 2 ].vm.$emit( 'update:modelValue', '12' );
		await nextTick();
		const exposed = wrapper.vm as unknown as { unparseableInputMessage(): string | null };
		expect( exposed.unparseableInputMessage() ).not.toBeNull();
	} );

	it( 'does not emit an empty model while a formerly valid date is temporarily invalid', async () => {
		const wrapper = input( '1458' );
		wrapper.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:modelValue', '0' );
		await nextTick();
		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		const exposed = wrapper.vm as unknown as { getCurrentValue(): { parts: string[] } };
		expect( exposed.getCurrentValue().parts ).toEqual( [ '1458' ] );
	} );

} );
