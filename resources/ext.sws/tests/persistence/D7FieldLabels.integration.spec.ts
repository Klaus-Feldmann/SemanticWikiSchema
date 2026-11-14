import { beforeEach, describe, expect, it } from 'vitest';
import fixture from '../../../../publication-tests/fixtures/d7-schema.json';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { PropertyName } from '@/domain/PropertyDefinition';
import { propertyDisplayName } from '@/presentation/propertyDisplayName';
import { setupMwMock } from '../VueTestHelpers';
describe( 'D7 schema persistence, order and labels', () => {
 beforeEach( () => setupMwMock() );
 it( 'saves and reloads reordered field keys independently of display language', () => {
  const reader = new SchemaDeserializer(); const writer = new SchemaSerializer();
  const original = reader.deserialize( 'S11', fixture );
  const reordered = original.withReorderedPropertyDefinitions( [ 'F12', 'Name', 'F4' ].map( key => new PropertyName( key ) ) );
  const saved = writer.schemaToJson( reordered );
  expect( Object.keys( saved.propertyDefinitions as object ) ).toEqual( [ 'F12', 'Name', 'F4' ] );
  const restored = reader.deserialize( 'S11', JSON.parse( JSON.stringify( saved ) ) );
  for ( const [ language, label ] of [ [ 'de', 'Geburtsdatum' ], [ 'en', 'Date of birth' ] ] ) {
   setupMwMock( { config: { wgUserLanguage: language } } );
   const field = restored.getPropertyDefinition( 'F4' );
   expect( propertyDisplayName( field ) ).toBe( label );
   expect( restored.getFieldReference( 'F4' ) ).toEqual( original.getFieldReference( 'F4' ) );
   expect( field.wikiLink?.labelTarget ).toBe( 'Attribut:Geburtsdatum' );
   expect( restored.getFieldReference( label ) ).toBeNull();
  }
 } );
 it( 'lets an existing descriptive technical key acquire labels without conversion to F numbers', () => {
  const data = structuredClone( fixture );
  data.propertyDefinitions.Name = { type: 'text', labels: { de: 'Vollständiger Name', en: 'Full name' } } as typeof data.propertyDefinitions.Name;
  const reader = new SchemaDeserializer(); const writer = new SchemaSerializer();
  const schema = reader.deserialize( 'S11', data );
  const reloaded = reader.deserialize( 'S11', JSON.parse( writer.serializeSchema( schema ) ) );
  expect( reloaded.getFieldReference( 'Name' ) ).toEqual( { schema: { id: 'S11' }, field: 'Name' } );
  expect( propertyDisplayName( reloaded.getPropertyDefinition( 'Name' ) ) ).toBe( 'Full name' );
 } );
} );
