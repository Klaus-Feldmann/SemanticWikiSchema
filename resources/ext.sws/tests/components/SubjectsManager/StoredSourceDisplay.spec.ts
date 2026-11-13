import { beforeEach, describe, expect, it } from 'vitest';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import type { ExternalField } from '@/composables/useExternalStatements';
import ExternalStatementDisplay from '@/components/SubjectsManager/ExternalStatementDisplay.vue';
import FieldNoteDisplay from '@/components/SubjectsManager/FieldNoteDisplay.vue';
import { sourceNoteForField } from '@/presentation/sourceState';

const property = newTextProperty( { name: 'Ort' } );
const field = (): ExternalField => ( { name: 'Ort', source: 'wikidata', propertyId: 'P19',
	status: 'resolved', sourceStatus: 'unavailable', storageMode: 'store', recorded: true, retained: true,
	values: [ { kind: 'text', value: 'Gespeicherter Ort' } ] } );

describe( 'stored source display', () => {
	beforeEach( () => setupMwMock( { functions: [ 'config', 'util', 'message' ] } ) );
	it( 'keeps the last available value visible and marks it as retained', () => {
		const wrapper = createTestWrapper( ExternalStatementDisplay, { field: field(), property } );
		expect( wrapper.text() ).toContain( 'Gespeicherter Ort' );
		expect( wrapper.text() ).toContain( 'sws-source-retained' );
		wrapper.unmount();
	} );
	it( 'shows the failed last attempt once alongside the author note', () => {
		const wrapper = createTestWrapper( FieldNoteDisplay, { property, text: 'Forschungsnotiz', source: field() } );
		expect( wrapper.text() ).toContain( 'Forschungsnotiz' );
		expect( wrapper.text().match( /sws-notes-source-unavailable/g ) ).toHaveLength( 1 );
		wrapper.unmount();
	} );
	it( 'distinguishes a pending initial store from a failed request', () => {
		const pending: ExternalField = { ...field(), recorded: false, retained: false, status: 'unavailable', values: [] };
		const wrapper = createTestWrapper( FieldNoteDisplay, { property, text: '', source: pending } );
		expect( wrapper.text() ).toContain( 'sws-source-pending' );
		expect( wrapper.text() ).not.toContain( 'sws-notes-source-unavailable' );
		wrapper.unmount();
	} );
	it( 'marks a locally available fallback language', () => {
		const wrapper = createTestWrapper( ExternalStatementDisplay, { field: { ...field(), language: 'de', languageFallback: true }, property } );
		expect( wrapper.text() ).toContain( 'sws-source-language-fallback' );
		expect( wrapper.text() ).toContain( 'Gespeicherter Ort' );
		wrapper.unmount();
	} );
	it( 'does not turn successful recovery into a source warning', () => {
		expect( sourceNoteForField( { ...field(), sourceStatus: 'resolved', retained: false } ) ).toBeUndefined();
	} );
	it( 'retains the existing cache-mode source hint behavior', () => {
		const cached = { ...field(), status: 'missing' as const, sourceStatus: undefined, storageMode: undefined };
		expect( sourceNoteForField( cached )?.status ).toBe( 'missing' );
	} );
} );
