import { describe, expect, it } from 'vitest';
import { Schema } from '@/domain/Schema';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty } from '@/domain/propertyTypes/Text';
const schema = ( id: string, label = 'Person' ): Schema => new Schema( id, '', new PropertyDefinitionList( [ newTextProperty( { name: 'F4' } ) ] ), false,
 { identityLanguage: 'de', labels: { de: label }, descriptions: {} }, id );
describe( 'Schema-local field references', () => {
 it( 'keeps identical numbers in different schemas separate', () => {
  expect( schema( 'S1' ).getFieldReference( 'F4' ) ).not.toEqual( schema( 'S2' ).getFieldReference( 'F4' ) );
 } );
 it( 'does not change the field reference when a schema label changes', () => {
  expect( schema( 'S1' ).getFieldReference( 'F4' ) ).toEqual( schema( 'S1', 'Mensch' ).getFieldReference( 'F4' ) );
 } );
 it( 'does not guess fields by case or display label', () => {
  expect( schema( 'S1' ).getFieldReference( 'f4' ) ).toBeNull();
  expect( schema( 'S1' ).getFieldReference( 'Person' ) ).toBeNull();
 } );
 it( 'does not reserve deleted keys globally', () => {
  expect( schema( 'S1' ).getFieldReference( 'F4' ) ).toEqual( { schema: { id: 'S1' }, field: 'F4' } );
  expect( () => new PropertyDefinitionList( [ newTextProperty( { name: 'F4' } ), newTextProperty( { name: 'F4' } ) ] ) ).toThrow();
 } );
} );
