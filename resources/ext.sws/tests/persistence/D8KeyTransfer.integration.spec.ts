import { describe, expect, it } from 'vitest';
import fixture from '../../../../publication-tests/fixtures/d7-schema.json';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { StatementList } from '@/domain/StatementList';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyName } from '@/domain/PropertyDefinition';
describe( 'D8 schema-key version persistence', () => {
 it( 'preserves history across immutable field edits and JSON reload', () => {
  const history = [ { Alt: 'F4' } ];
  const reader = new SchemaDeserializer(); const writer = new SchemaSerializer();
  const initial = reader.deserialize( 'S11', { ...fixture, fieldKeyChanges: history } );
  const edited = initial.withDescription( 'Neue Beschreibung' ).withAddedPropertyDefinition( newTextProperty( { name: 'Z1' } ) ).withRemovedPropertyDefinition( new PropertyName( 'Z1' ) );
  const restored = reader.deserialize( 'S11', JSON.parse( writer.serializeSchema( edited ) ) );
  expect( restored.getFieldKeyChanges() ).toEqual( history );
  expect( restored.getPropertyDefinition( 'F4' ).labels ).toEqual( fixture.propertyDefinitions.F4.labels );
 } );
 it( 'retains the opened key version while filtering editor statements', () => {
  const list = new StatementList( [], {}, false, 3 );
  expect( list.withNonEmptyValues().fieldKeyVersion ).toBe( 3 );
  const schema = new SchemaDeserializer().deserialize( 'S11', fixture );
  expect( schema.statementsFrom( list ).fieldKeyVersion ).toBe( 3 );
 } );
} );
