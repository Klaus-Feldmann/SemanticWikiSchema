/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { CdxButton, CdxTextArea } from '@wikimedia/codex';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
import { newSchema, newSubject } from '@/TestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyName, createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { StatementList } from '@/domain/StatementList';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import { StatementDeserializer } from '@/persistence/StatementDeserializer';
import { Neo } from '@/Neo';
import FieldNoteInput from '@/components/SubjectEditor/FieldNoteInput.vue';
import SubjectEditor, { type SubjectEditorExposes } from '@/components/SubjectEditor/SubjectEditor.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import FieldNoteDisplay from '@/components/SubjectsManager/FieldNoteDisplay.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
beforeEach( () => { setActivePinia( createPinia() ); setupMwMock( { functions: [ 'config', 'util', 'message' ] } ); } );
afterEach( () => vi.unstubAllGlobals() );
const definition = () => ( { ...newTextProperty( { name: 'Geburtsort' } ), fieldNotes: { type: 'text' as const, annotate: true } } );
const schema = () => newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ definition() ] ) } );
describe( 'field-bound author notes', () => {
 it( 'offers a compact add button and opens a textarea only when requested', async () => {
  const w = createTestWrapper( FieldNoteInput, { modelValue: '' } );
  expect( w.findComponent( CdxTextArea ).exists() ).toBe( false );
  await w.findComponent( CdxButton ).trigger( 'click' );
  w.findComponent( CdxTextArea ).vm.$emit( 'update:modelValue', '[[Brünn]]' );
  expect( w.emitted( 'update:modelValue' ) ).toEqual( [ [ '[[Brünn]]' ] ] );
 } );
 it( 'opens an existing note immediately', () => {
  const w = createTestWrapper( FieldNoteInput, { modelValue: 'Beleg fehlt' } );
  expect( w.findComponent( CdxTextArea ).props( 'modelValue' ) ).toBe( 'Beleg fehlt' );
  expect( w.findComponent( CdxButton ).exists() ).toBe( false );
 } );
 it( 'keeps a note for an empty field when harvesting and filtering the form', async () => {
  const s = schema(); const w = createTestWrapper( SubjectEditor, { schema: s, statements: s.blankStatements() } );
  await w.findComponent( FieldNoteInput ).vm.$emit( 'update:modelValue', 'Noch offen' );
  const result = ( w.vm as unknown as SubjectEditorExposes ).getSubjectData().withNonEmptyValues();
  expect( [ ...result ] ).toEqual( [] );
  expect( result.fieldNotes ).toEqual( { Geburtsort: 'Noch offen' } );
  expect( result.notesEdited ).toBe( true );
  expect( w.emitted( 'change' ) ).toBeTruthy();
 } );
 it( 'preserves notes through schema editing and marks explicit removal', () => {
  const s = schema(); const existing = new StatementList( [], { Geburtsort: 'Alt', Unbekannt: 'Erhalten' } );
  expect( s.statementsFrom( existing ).fieldNotes ).toEqual( existing.fieldNotes );
  const edited = new StatementList( [], { Unbekannt: 'Erhalten' }, true );
  const saved = s.statementsAfterEdit( existing, edited ).withNonEmptyValues();
  expect( saved.fieldNotes ).toEqual( { Unbekannt: 'Erhalten' } ); expect( saved.notesEdited ).toBe( true );
 } );
 it( 'serializes field configuration separately from the value type', () => {
  const p = createPropertyDefinitionFromJson( 'Ort', { type: 'text', fieldNotes: { type: 'wikitext', annotate: true } } );
  const s = newSchema( { properties: new PropertyDefinitionList( [ p ] ) } );
  expect( ( new SchemaSerializer().schemaToJson( s ).propertyDefinitions as any ).Ort.fieldNotes ).toEqual( { type: 'wikitext', annotate: true } );
 } );
 it( 'reads notes from a subject response without manufacturing a field value', () => {
  const reader = new SubjectDeserializer( new StatementDeserializer( Neo.getInstance().getValueDeserializer() ) );
  const subject = reader.deserialize( { id: 's11111111111111', schema: 'Person', displayName: 'Test', displayNameIsGenerated: false, pageId: 1, pageTitle: 'Test', statements: {}, fieldNotes: { Geburtsort: 'Notiz' } } );
  expect( subject.getStatements().fieldNotes.Geburtsort ).toBe( 'Notiz' ); expect( subject.getNamesOfNonEmptyProperties() ).toEqual( [] );
 } );
 it( 'shows a note-only field on Data without rendering plain-text markup', async () => {
  useSchemaStore().schemas.set( 'Person', schema() );
  const subject = newSubject( { schemaName: 'Person', statements: new StatementList( [], { Geburtsort: '[[Brünn]] <b>Quelle</b>' } ) } );
  const w = createTestWrapper( SubjectStatementsView, { subject } ); await flushPromises();
  expect( w.get( 'dt' ).text() ).toBe( 'Geburtsort' ); expect( w.text() ).toContain( '[[Brünn]] <b>Quelle</b>' ); expect( w.find( 'b' ).exists() ).toBe( false );
 } );
 it( 'shows exactly one source note alongside a distinct author note', () => {
  const w = createTestWrapper( FieldNoteDisplay, { property: definition(), text: 'Forschungsnotiz', source: { name: 'Geburtsort', source: 'wikidata', propertyId: 'P19', status: 'unavailable', values: [] } } );
  expect( w.text() ).toContain( 'Forschungsnotiz' );
  expect( w.text().match( /sws-notes-source-unavailable/g ) ).toHaveLength( 1 );
  expect( w.text() ).not.toContain( 'sws-derived-unavailable' );
 } );
} );
