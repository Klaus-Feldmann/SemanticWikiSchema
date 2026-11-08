import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxCheckbox, CdxSelect } from '@wikimedia/codex';
import PropertySourceEditor from '@/components/SchemaEditor/Property/PropertySourceEditor.vue';
import IdLookup from '@/components/Id/IdLookup.vue';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
vi.mock( '@/persistence/PropertyIdLookup', () => ( { lookupPropertyIds: vi.fn().mockResolvedValue( [] ) } ) );
describe( 'optional schema property ID assignment', () => {
	beforeEach( () => setupMwMock() );
	afterEach( () => vi.restoreAllMocks() );
	it( 'leaves ordinary text fields alone until enabled', async () => {
		const field = newTextProperty( { name: 'Note' } );
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		expect( w.findComponent( IdLookup ).exists() ).toBe( false );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		expect( w.emitted( 'update:property' ) ).toBeUndefined();
		await w.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', true );
		expect( w.vm.unparseableInputMessage() ).not.toBeNull();
		w.unmount();
	} );
	it( 'clears the old reference when the source changes, without reusing its numeric ID', async () => {
		const field = { ...newTextProperty(), externalProperty: { source: 'wikidata' as const, id: 'P31' } };
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'wikibase-local' );
		expect( w.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { externalProperty: undefined } ] );
		expect( w.findComponent( IdLookup ).props( 'source' ) ).toBe( 'wikibase-local' );
		w.unmount();
	} );
	it( 'removes the assignment explicitly when disabled', async () => {
		const field = { ...newTextProperty(), externalProperty: { source: 'wikidata' as const, id: 'P31' } };
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		await w.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', false );
		expect( w.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { externalProperty: undefined } ] );
		expect( w.vm.unparseableInputMessage() ).toBeNull();
		w.unmount();
	} );
	it( 'defaults the value link target to the source without changing the schema', () => {
		const field = { ...newTextProperty(), externalProperty: { source: 'wikidata' as const, id: 'P31' } };
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		expect( w.findAllComponents( CdxSelect )[ 1 ].props( 'selected' ) ).toBe( 'source' );
		expect( w.emitted( 'update:property' ) ).toBeUndefined();
		w.unmount();
	} );
	it( 'preserves other link settings when selecting a local value target', async () => {
		const field = { ...newTextProperty(), externalProperty: { source: 'wikidata' as const, id: 'P31' },
			wikiLink: { namespace: 'Werk', label: false, values: true } };
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		await w.findAllComponents( CdxSelect )[ 1 ].vm.$emit( 'update:selected', 'local' );
		expect( w.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { wikiLink: { ...field.wikiLink, externalTarget: 'local' } } ] );
		w.unmount();
	} );
	it( 'displays a previously saved local value target', () => {
		const field = { ...newTextProperty(), externalProperty: { source: 'wikidata' as const, id: 'P31' },
			wikiLink: { externalTarget: 'local' as const } };
		const w = createTestWrapper( PropertySourceEditor, { property: field } );
		expect( w.findAllComponents( CdxSelect )[ 1 ].props( 'selected' ) ).toBe( 'local' );
		w.unmount();
	} );

} );
