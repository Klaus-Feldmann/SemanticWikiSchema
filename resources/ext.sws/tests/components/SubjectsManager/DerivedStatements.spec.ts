import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { newSchema, newSubject } from '@/TestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { type PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newStringValue } from '@/domain/Value';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
import type { ExternalField } from '@/composables/useExternalStatements';
const sid = 's1aaaaaaaaaaaa1';
const field: ExternalField = { name: 'Klassifizierung', source: 'wikidata', propertyId: 'P31',
	propertyLabel: 'ist ein(e)', propertyUrl: 'https://www.wikidata.org/wiki/Property:P31', status: 'resolved',
	values: [ { kind: 'entity', id: 'Q5', label: 'Mensch', url: 'https://www.wikidata.org/wiki/Q5' } ] };
let currentField: ExternalField;
let mainSubject = sid;
let outage = false;
function show( own?: string, source: 'wikidata' | 'wikibase-local' = 'wikidata', bound = true, wikiLink: PropertyDefinition['wikiLink'] = undefined ): { wrapper: VueWrapper; subject: ReturnType<typeof newSubject> } {
	const definition = { ...newTextProperty( { name: 'Klassifizierung' } ), wikiLink, externalProperty: bound ? { source, id: 'P31' } : undefined };
	const schema = newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ definition ] ) } );
	useSchemaStore().schemas.set( 'Person', schema );
	const subject = newSubject( { id: sid, schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ),
		statements: new StatementList( own === undefined ? [] : [ new Statement( new PropertyName( 'Klassifizierung' ), 'text', newStringValue( own ) ) ] ) } );
	return { wrapper: createTestWrapper( SubjectStatementsView, { subject } ), subject };
}
describe( 'read-only derived entity values in the data view', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'config', 'util' ] } );
		mw.util.wikiScript = () => '/w/api.php';
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
		currentField = structuredClone( field );
		mainSubject = sid;
		outage = false;
		vi.stubGlobal( 'fetch', vi.fn().mockImplementation( async ( url ) => {
			if ( !String( url ).includes( 'action=swsbasevalues' ) ) {
				return { ok: false, json: async () => ( {} ) };
			}
			return { ok: !outage, json: async () => ( { swsbasevalues: {
				version: 1, scope: 'current', pageId: 1152, mainSubjectId: mainSubject, fields: [ currentField ],
			} } ) };
		} ) );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'shows an empty stored field from its assignment, with property and value links', async () => {
		const { wrapper: w, subject } = show();
		await flushPromises();
		expect( w.text() ).toContain( 'Klassifizierung' );
		expect( w.text() ).toContain( 'Wikidata:' );
		expect( w.get( 'a[href="https://www.wikidata.org/wiki/Property:P31"]' ).text() ).toBe( 'ist ein(e)' );
		expect( w.get( 'a[href="https://www.wikidata.org/wiki/Q5"]' ).text() ).toBe( 'Mensch' );
		expect( subject.getNamesOfNonEmptyProperties() ).toEqual( [] );
		w.unmount();
	} );
	it( 'retains an entered property ID without inventing a local page link or overwriting it', async () => {
		const { wrapper: w, subject } = show( 'P31' );
		await flushPromises();
		expect( w.text() ).toContain( 'sws-derived-own' );
		expect( w.text() ).toContain( 'Mensch' );
		expect( w.findAll( 'a' ).some( ( a ) => a.attributes( 'href' )?.endsWith( '/P31' ) ) ).toBe( false );
		expect( subject.getStatementValue( new PropertyName( 'Klassifizierung' ) ) ).toEqual( newStringValue( 'P31' ) );
		w.unmount();
	} );
	it( 'keeps source-qualified local links separate even for the same Q and P numbers', async () => {
		currentField = { ...field, source: 'wikibase-local', propertyLabel: 'Lokale Klasse',
			propertyUrl: window.location.origin + '/wiki/Property:P31', values: [ { kind: 'entity', id: 'Q5', label: 'Lokaler Wert', url: window.location.origin + '/wiki/Item:Q5' } ] };
		const { wrapper: w } = show( undefined, 'wikibase-local' );
		await flushPromises();
		expect( w.text() ).toContain( 'Wikibase:' );
		expect( w.text() ).toContain( 'Lokaler Wert' );
		expect( w.findAll( 'a' ).some( ( a ) => a.attributes( 'href' )?.includes( 'www.wikidata.org' ) ) ).toBe( false );
		w.unmount();
	} );
	it( 'shows an explicit source-missing status without inventing a value', async () => {
		currentField.status = 'missing';
		currentField.values = [];
		const { wrapper: w } = show();
		await flushPromises();
		expect( w.find( 'dt' ).exists() ).toBe( true );
		expect( w.text() ).toContain( 'sws-notes-source-missing' );
		expect( w.text() ).not.toContain( 'Mensch' );
		w.unmount();
	} );
	it( 'shows source unavailability separately from a confirmed missing value', async () => {
		currentField.status = 'unavailable';
		currentField.values = [];
		const { wrapper: w } = show();
		await flushPromises();
		expect( w.text().match( /sws-notes-source-unavailable/g ) ).toHaveLength( 1 );
		expect( w.text() ).not.toContain( 'sws-notes-source-missing' );
		w.unmount();
	} );
	it( 'does not mistake an empty stored string list for a manual source override', async () => {
        currentField.status = 'unavailable'; currentField.values = [];
        const { wrapper: w } = show( '' ); await flushPromises();
        expect( w.text().match( /sws-notes-source-unavailable/g ) ).toHaveLength( 1 );
        expect( w.text() ).not.toContain( 'sws-derived-own' ); w.unmount();
    } );
	it( 'renders language-tagged source text without converting it into a page link', async () => {
		currentField = { ...field,
			values: [ { kind: 'text', value: 'Avram Noam Chomsky', language: 'en', rank: 'normal' } ] };
		const { wrapper: w } = show();
		await flushPromises();
		const text = w.find( '[lang="en"]' );
		expect( text.exists() ).toBe( true );
		expect( text.text() ).toContain( 'Avram Noam Chomsky' );
		expect( w.find( 'a[href="https://www.wikidata.org/wiki/Property:P31"]' ).exists() ).toBe( true );
		expect( w.findAll( 'a' ).some( ( a ) => a.text() === 'Avram Noam Chomsky' ) ).toBe( false );
		w.unmount();
	} );
	it( 'does not assign the current page entity to a different subject', async () => {
		mainSubject = 's2aaaaaaaaaaaa2';
		const { wrapper: w } = show();
		await flushPromises();
		expect( w.text() ).not.toContain( 'Mensch' );
		w.unmount();
	} );
	it( 'rejects a value link to the wrong source', async () => {
		currentField.values[ 0 ].url = window.location.origin + '/wiki/Q5';
		const { wrapper: w } = show();
		await flushPromises();
		expect( w.text().match( /sws-notes-source-unavailable/g ) ).toHaveLength( 1 );
		expect( w.text() ).not.toContain( 'Mensch' );
		w.unmount();
	} );
	it( 'keeps own values on a failed request', async () => {
		outage = true;
		const { wrapper: w, subject } = show( 'Eigene Forschung' );
		await flushPromises();
		expect( w.text() ).toContain( 'Eigene Forschung' );
		expect( w.text() ).not.toContain( 'sws-notes-source-unavailable' );
		expect( subject.getStatementValue( new PropertyName( 'Klassifizierung' ) ) ).toEqual( newStringValue( 'Eigene Forschung' ) );
		w.unmount();
	} );
	it( 'does not fetch derived values for fields without an assignment', async () => {
		const { wrapper: w } = show( 'Eigene Forschung', 'wikidata', false );
		await flushPromises();
		expect( vi.mocked( fetch ).mock.calls.some( ( [ url ] ) => String( url ).includes( 'action=swsbasevalues' ) ) ).toBe( false );
		w.unmount();
	} );
	it( 'does not fetch current external values for a historical revision', async () => {
		setupMwMock( { functions: [ 'config', 'util' ], config: { wgRevisionId: 10, wgCurRevisionId: 11 } } );
		const { wrapper: w } = show();
		await flushPromises();
		expect( fetch ).not.toHaveBeenCalled();
		w.unmount();
	} );
	it( 'links the internal label to its configured local target independently of source P and value links', async () => {
		const { wrapper: w } = show( undefined, 'wikidata', true, { label: true, labelTarget: 'Vermerk:Klassifizierung', namespace: 'Vermerk', externalTarget: 'local' } );
		await flushPromises();
		expect( w.get( 'dt a' ).attributes( 'href' ) ).toBe( '/wiki/Vermerk%3AKlassifizierung' );
		expect( w.get( 'dt a' ).text() ).toBe( 'Klassifizierung' );
		expect( w.get( 'a[href="https://www.wikidata.org/wiki/Property:P31"]' ).text() ).toBe( 'ist ein(e)' );
		expect( w.get( 'a[href="/wiki/Vermerk%3AMensch"]' ).text() ).toBe( 'Mensch' );
		w.unmount();
	} );
	it( 'keeps a disabled internal label plain while preserving derived links', async () => {
		const { wrapper: w } = show( undefined, 'wikidata', true, { label: false, labelTarget: 'Vermerk:Klassifizierung' } );
		await flushPromises();
		expect( w.find( 'dt a' ).exists() ).toBe( false );
		expect( w.get( 'dt' ).text() ).toBe( 'Klassifizierung' );
		expect( w.find( 'a[href="https://www.wikidata.org/wiki/Q5"]' ).exists() ).toBe( true );
		w.unmount();
	} );

} );
