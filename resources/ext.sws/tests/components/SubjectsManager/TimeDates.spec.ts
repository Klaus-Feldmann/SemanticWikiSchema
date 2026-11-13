import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import type { ExternalField } from '@/composables/useExternalStatements';
import ExternalStatementDisplay from '@/components/SubjectsManager/ExternalStatementDisplay.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import WikiLinkEditor from '@/components/SchemaEditor/Property/WikiLinkEditor.vue';
import { CdxTextInput } from '@wikimedia/codex';
import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newStringValue } from '@/domain/Value';
import { useSchemaStore } from '@/stores/SchemaStore';
import { dateParts } from '@/domain/propertyTypes/datePresentation';
import { parseHistoricalDate } from '@/domain/propertyTypes/historicalDate';
import { newSchema, newSubject } from '@/TestHelpers';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
const property = (): PropertyDefinition => ( { name: new PropertyName( 'Geburtsdatum' ), type: 'historicalDate', description: '', required: false,
	externalProperty: { source: 'wikidata', id: 'P569' }, wikiLink: { dateBceEn: 'BCE' } } );
const timeField = (): ExternalField => ( { name: 'Geburtsdatum', source: 'wikidata', propertyId: 'P569', propertyLabel: 'Geburtsdatum', status: 'resolved',
	values: [ { kind: 'time', year: -347, precision: 9, calendar: 'julian', sourceTime: { time: '-0347-00-00T00:00:00Z' },
		parts: [ { text: '347 BCE', yearTitle: '347 v. Chr.' } ] } ] } );
describe( 'historical time values', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'config', 'util', 'message' ], config: { wgUserLanguage: 'en', wgContentLanguage: 'de' } } );
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
		mw.util.wikiScript = () => '/w/api.php';
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { swsbasevalues: {
			version: 1, scope: 'current', pageId: 1152, mainSubjectId: 's1aaaaaaaaaaaa1', fields: [ timeField() ],
		} } ) } ) );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'uses configured text independently of the year page', () => {
		const w = createTestWrapper( ExternalStatementDisplay, { field: timeField(), property: property() } );
		expect( w.text() ).toContain( '347 BCE' );
		expect( w.get( 'a' ).attributes( 'href' ) ).toBe( '/wiki/347%20v.%20Chr.' );
		w.unmount();
	} );
	it( 'gives an own date priority, counts the field once, then restores the automatic date', async () => {
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ property() ] ) } ) );
		const make = ( own: boolean ): ReturnType<typeof newSubject> => newSubject( { id: 's1aaaaaaaaaaaa1', schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ),
			statements: new StatementList( own ? [ new Statement( new PropertyName( 'Geburtsdatum' ), 'historicalDate', newStringValue( '-0346' ) ) ] : [] ) } );
		const w = createTestWrapper( SubjectStatementsView, { subject: make( true ) } );
		await flushPromises();
		expect( w.text() ).toContain( '346 BCE' );
		expect( w.text() ).not.toContain( '347 BCE' );
		expect( w.emitted( 'count-change' )?.slice( -1 )[ 0 ] ).toEqual( [ 1 ] );
		await w.setProps( { subject: make( false ) } );
		await flushPromises();
		expect( w.text() ).toContain( '347 BCE' );
		expect( w.emitted( 'count-change' )?.slice( -1 )[ 0 ] ).toEqual( [ 1 ] );
		w.unmount();
	} );
	it( 'shows a manual instruction and does not count it as a resolved value', async () => {
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { swsbasevalues: {
			version: 1, scope: 'current', pageId: 1152, mainSubjectId: 's1aaaaaaaaaaaa1', fields: [ { ...timeField(), status: 'manual', values: [] } ],
		} } ) } ) );
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ property() ] ) } ) );
		const w = createTestWrapper( SubjectStatementsView, { subject: newSubject( { id: 's1aaaaaaaaaaaa1', schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ) } ) } );
		await flushPromises();
		expect( w.text() ).toContain( 'sws-notes-source-manual' );
		expect( w.emitted( 'count-change' )?.slice( -1 )[ 0 ] ).toEqual( [ 0 ] );
		w.unmount();
	} );
	it( 'provides editable DE and EN suffixes as schema options', async () => {
		const w = createTestWrapper( WikiLinkEditor, { property: property() } );
		const input = w.findAllComponents( CdxTextInput ).find( ( i ) => i.props( 'modelValue' ) === 'BCE' );
		expect( input ).toBeDefined();
		input!.vm.$emit( 'update:modelValue', 'BC' );
		await flushPromises();
		expect( w.emitted( 'update:property' )?.slice( -1 )[ 0 ] ).toEqual( [ { wikiLink: { dateBceEn: 'BC' } } ] );
		w.unmount();
	} );
	it( 'uses the historical calendar convention for own input and independent link labels', () => {
		expect( parseHistoricalDate( '1500-02-29' ) ).not.toBeNull();
		expect( parseHistoricalDate( '1700-02-29' ) ).toBeNull();
		expect( parseHistoricalDate( '1582-10-10' ) ).toBeNull();
		expect( dateParts( { year: -347 }, property(), 'en', 'de' ) ).toEqual( [ { text: '347 BCE', yearTitle: '347 v. Chr.' } ] );
	} );
} );
