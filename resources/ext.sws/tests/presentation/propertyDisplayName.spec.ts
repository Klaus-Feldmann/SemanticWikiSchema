import { beforeEach, describe, expect, it } from 'vitest';
import { propertyDisplayName } from '@/presentation/propertyDisplayName';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { setupMwMock } from '../VueTestHelpers';
describe( 'Field labels', () => {
 beforeEach( () => setupMwMock( { config: { wgUserLanguage: 'de', wgContentLanguage: 'de' } } ) );
 it( 'shows the selected language without renaming the key', () => {
  const p = newTextProperty( { name: 'F4', labels: { de: 'Geburtsdatum', en: 'Date of birth' } } );
  expect( propertyDisplayName( p ) ).toBe( 'Geburtsdatum' ); expect( p.name.toString() ).toBe( 'F4' );
  setupMwMock( { config: { wgUserLanguage: 'en', wgContentLanguage: 'de' } } );
  expect( propertyDisplayName( p ) ).toBe( 'Date of birth' );
 } );
 it( 'falls back through core languages and then content language', () => {
  setupMwMock( { config: { wgUserLanguage: 'fr', wgContentLanguage: 'de' } } );
  expect( propertyDisplayName( newTextProperty( { name: 'F4', labels: { de: 'Datum' } } ) ) ).toBe( 'Datum' );
 } );
 it( 'falls back to the technical key, never an inherited object value', () => {
  expect( propertyDisplayName( newTextProperty( { name: 'F4' } ) ) ).toBe( 'F4' );
  expect( propertyDisplayName( newTextProperty( { name: 'F4', labels: {} } ) ) ).toBe( 'F4' );
 } );
 it( 'does not choose empty translations', () => {
  expect( propertyDisplayName( newTextProperty( { name: 'F4', labels: { de: ' ', en: 'Date' } } ) ) ).toBe( 'Date' );
 } );
} );
