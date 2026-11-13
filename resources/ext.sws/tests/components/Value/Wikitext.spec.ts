/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { CdxCheckbox, CdxTextArea } from '@wikimedia/codex';
import { createTestWrapper } from '../../VueTestHelpers';
import WikitextInput from '@/components/Value/WikitextInput.vue';
import WikitextDisplay from '@/components/Value/WikitextDisplay.vue';
import WikitextAttributesEditor from '@/components/SchemaEditor/Property/WikitextAttributesEditor.vue';
import { createPropertyDefinitionFromJson, PropertyName } from '@/domain/PropertyDefinition';
import { newWikitextValue, type WikitextProperty, isSchemaExecution } from '@/domain/propertyTypes/Wikitext';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
import type { AttributesEditorExposes } from '@/components/SchemaEditor/Property/AttributesEditorContract';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { Neo } from '@/Neo';
import { newSubject } from '@/TestHelpers';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { resolveAllNonEmptyProperties } from '@/domain/resolveDisplayProperties';
beforeEach( () => vi.stubGlobal( 'mw', { msg: ( key: string ) => key, message: ( key: string ) => ( { text: () => key } ) } ) );
afterEach( () => vi.unstubAllGlobals() );
function property( attributes = {} ) {
	return createPropertyDefinitionFromJson( 'Verarbeitung', { type: 'wikitext', ...attributes } ) as WikitextProperty;
}
function schema( execute = true ) {
	return new SchemaDeserializer().deserialize( 'Person', { propertyDefinitions: {
		Verarbeitung: { type: 'wikitext', execute, wikitext: '{{SWS}}' }, Notiz: { type: 'text' },
	} } );
}
describe( 'Wikitext modes', () => {
	it( 'registers a string-backed type which always represents a schema template', () => {
		expect( property() ).not.toHaveProperty( 'execute' );
		expect( isSchemaExecution( property() ) ).toBe( true );
		expect( Neo.getInstance().getPropertyTypeRegistry().getType( 'wikitext' ).getValueType() ).toBe( 'string' );
	} );
	it( 'preserves meaningful whitespace and multiline source through serialization', () => {
		const value = '  vorformatiert\n{{Beispiel|x=1}}\n';
		expect( Neo.getInstance().getValueDeserializer().deserialize( [ value ], 'wikitext' ) ).toEqual( newWikitextValue( value ) );
		expect( newWikitextValue( value ).parts[ 0 ] ).toBe( value );
	} );
	it( 'rejects malformed value shapes', () => {
		expect( () => Neo.getInstance().getValueDeserializer().deserialize( { code: 'x' }, 'wikitext' ) ).toThrow();
	} );
	it( 'edits source without trimming indentation or executing markup', async () => {
		const w = createTestWrapper( WikitextInput, { property: property(), modelValue: undefined } );
		w.findComponent( CdxTextArea ).vm.$emit( 'update:modelValue', '  {{SWS}}\n' );
		await w.vm.$nextTick();
		expect( ( w.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newWikitextValue( '  {{SWS}}\n' ) );
	} );
	it( 'renders ordinary wikitext as escaped source', () => {
		const w = createTestWrapper( WikitextDisplay, { property: property(), value: newWikitextValue( '<b>{{SWS}}</b>' ) } );
		expect( w.find( 'b' ).exists() ).toBe( false );
		expect( w.text() ).toBe( '<b>{{SWS}}</b>' );
	} );
	it( 'does not offer an execution switch', () => {
		const w = createTestWrapper( WikitextAttributesEditor, { property: property() } );
		expect( w.findComponent( CdxCheckbox ).exists() ).toBe( false );
	} );
	it( 'blocks an empty schema program', () => {
		const w = createTestWrapper( WikitextAttributesEditor, { property: property( { execute: true, wikitext: ' ' } ) } );
		expect( ( w.vm as unknown as AttributesEditorExposes ).unparseableInputMessage!() ).toBeTruthy();
	} );
	it( 'accepts an explicit schema program', () => {
		const w = createTestWrapper( WikitextAttributesEditor, { property: property( { execute: true, wikitext: '{{SWS}}' } ) } );
		expect( ( w.vm as unknown as AttributesEditorExposes ).unparseableInputMessage!() ).toBeNull();
	} );
	it( 'retains the program and mode after saving and reopening the schema', () => {
		const saved = new SchemaSerializer().schemaToJson( schema() );
		const reopened = new SchemaDeserializer().deserialize( 'Person', saved );
		expect( reopened.getPropertyDefinition( 'Verarbeitung' ) ).toMatchObject( { type: 'wikitext', wikitext: '{{SWS}}' } );
	} );
	it( 'treats legacy inactive schema code as a template', () => {
		expect( schema( false ).getPropertyDefinition( 'Verarbeitung' ) ).toMatchObject( { wikitext: '{{SWS}}' } );
	} );
	it( 'does not create an input or statement for a schema program', () => {
		expect( [ ...schema().blankStatements() ].map( ( s ) => s.propertyName.toString() ) ).toEqual( [ 'Notiz' ] );
	} );
	it( 'legacy template fields remain hidden subject inputs', () => {
		expect( [ ...schema( false ).blankStatements() ].map( ( s ) => s.propertyName.toString() ) ).toEqual( [ 'Notiz' ] );
	} );
	it( 'preserves dormant subject values when editing other fields in execution mode', () => {
		const old = new StatementList( [ new Statement( new PropertyName( 'Verarbeitung' ), 'wikitext', newWikitextValue( '{{NichtAusfuehren}}' ) ) ] );
		const after = schema().statementsAfterEdit( old, new StatementList( [] ) );
		expect( after.get( new PropertyName( 'Verarbeitung' ) ).value ).toEqual( newWikitextValue( '{{NichtAusfuehren}}' ) );
	} );
	it( 'hides dormant values from the Data view and its field count', () => {
		const statements = new StatementList( [ new Statement( new PropertyName( 'Verarbeitung' ), 'wikitext', newWikitextValue( '{{NichtAusfuehren}}' ) ) ] );
		expect( resolveAllNonEmptyProperties( schema(), newSubject( { statements } ) ) ).toEqual( [] );
		expect( resolveAllNonEmptyProperties( schema( false ), newSubject( { statements } ) ) ).toHaveLength( 0 );
	} );
} );
