import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { newSchema, newSubject } from '@/TestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newStringValue } from '@/domain/Value';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';

describe( 'schema text defaults in Data', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'config', 'util', 'message' ] } );
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: false, json: async () => ( {} ) } ) );
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [
			newTextProperty( { name: 'Neu', default: newStringValue( 'SWS Migration Feld ergänzt' ) } ),
		] ) } ) );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'shows the schema placeholder without creating a subject statement', async () => {
		const subject = newSubject( { schemaName: 'Person', statements: new StatementList( [] ) } );
		const wrapper = createTestWrapper( SubjectStatementsView, { subject } );
		await flushPromises();
		expect( wrapper.text() ).toContain( 'SWS Migration Feld ergänzt' );
		expect( wrapper.text() ).toContain( 'sws-schema-default-value' );
		expect( subject.getStatements().has( new PropertyName( 'Neu' ) ) ).toBe( false );
		expect( vi.mocked( fetch ).mock.calls.some( ( [ url ] ) => String( url ).includes( 'action=swsbasevalues' ) ) ).toBe( false );
		wrapper.unmount();
	} );
	it( 'uses an author value instead of the schema placeholder', async () => {
		const subject = newSubject( { schemaName: 'Person', statements: new StatementList( [
			new Statement( new PropertyName( 'Neu' ), 'text', newStringValue( 'Eigene Forschung' ) ),
		] ) } );
		const wrapper = createTestWrapper( SubjectStatementsView, { subject } );
		await flushPromises();
		expect( wrapper.text() ).toContain( 'Eigene Forschung' );
		expect( wrapper.text() ).not.toContain( 'SWS Migration Feld ergänzt' );
		expect( wrapper.text() ).not.toContain( 'sws-schema-default-value' );
		wrapper.unmount();
	} );
} );
