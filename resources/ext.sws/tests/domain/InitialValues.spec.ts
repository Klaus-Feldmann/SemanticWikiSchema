import { describe, it, expect } from 'vitest';
import { reactive } from 'vue';
import { newSchema } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newUrlProperty } from '@/domain/propertyTypes/Url';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newBooleanProperty } from '@/domain/propertyTypes/Boolean';
import { newStringValue, newUrlValue, newNumberValue, newBooleanValue, valueToJson, type UrlValue } from '@/domain/Value';
import { StatementList } from '@/domain/StatementList';
import { PropertyName } from '@/domain/PropertyDefinition';

describe( 'Schema initial values', () => {
 it( 'copies defaults only for new subjects and preserves zero and false', () => {
  const schema = newSchema( { properties: new PropertyDefinitionList( [
   newTextProperty( { name: 'Text', default: newStringValue( 'Anfang' ) } ),
   newNumberProperty( { name: 'Zahl', default: newNumberValue( 0 ) } ),
   newBooleanProperty( { name: 'Bool', default: newBooleanValue( false ) } ),
  ] ) } );
  expect( [ ...schema.initialStatements() ].map( ( s ) => valueToJson( s.value! ) ) ).toEqual( [ [ 'Anfang' ], 0, false ] );
  expect( [ ...schema.statementsFrom( new StatementList( [] ) ) ].every( ( s ) => s.value === undefined ) ).toBe( true );
  const cleared = schema.initialStatements().get( new PropertyName( 'Text' ) ).withValue( undefined );
  expect( schema.statementsFrom( new StatementList( [ cleared ] ) ).get( cleared.propertyName ).value ).toBeUndefined();
 } );
 it( 'keeps incomplete URL presets and clones them for each form, including Vue proxies', () => {
  const preset = newUrlValue( [ { url: '', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia (de)' } ], true );
  const schema = reactive( newSchema( { properties: new PropertyDefinitionList( [ newUrlProperty( { name: 'Quelle', default: preset } ) ] ) } ) );
  const a = [ ...schema.initialStatements() ][ 0 ].value as UrlValue;
  const b = [ ...schema.initialStatements() ][ 0 ].value as UrlValue;
  a.parts[ 0 ].caption = 'Andere Quelle';
  expect( b.parts[ 0 ].caption ).toBe( 'Wikipedia (de)' );
  expect( preset.parts[ 0 ].caption ).toBe( 'Wikipedia (de)' );
  expect( () => newUrlValue( [ { url: '', caption: 'Quelle' } ] ) ).toThrow();
 } );
} );

describe( 'URL schema persistence', () => {
 it( 'round-trips incomplete URL metadata through the schema serializer and deserializer', async () => {
  const { SchemaSerializer } = await import( '@/persistence/SchemaSerializer' );
  const { SchemaDeserializer } = await import( '@/persistence/SchemaDeserializer' );
  const schema = newSchema( { properties: new PropertyDefinitionList( [ newUrlProperty( {
   name: 'Quelle', default: newUrlValue( [ { url: '', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia (de)' } ], true ),
  } ) ] ) } );
  const serializer = new SchemaSerializer();
  const json = serializer.schemaToJson( schema );
  const restored = new SchemaDeserializer().deserialize( 'Person', json );
  expect( serializer.schemaToJson( restored ) ).toEqual( json );
  expect( ( [ ...restored.initialStatements() ][ 0 ].value as UrlValue ).parts[ 0 ].captionPage ).toBe( 'Vermerk:Wikipedia (de)' );
 } );
} );
