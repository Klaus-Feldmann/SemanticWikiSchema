import { beforeEach, describe, expect, it } from 'vitest';
import PropertyLabel from '@/components/common/PropertyLabel.vue';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
describe( 'D6 field link captions', () => {
 beforeEach( () => setupMwMock( { config: { wgUserLanguage: 'de' }, functions: [ 'config', 'language', 'util', 'message' ] } ) );
 it( 'uses translated text while retaining the configured link destination', () => {
  const property = createPropertyDefinitionFromJson( 'F4', { type: 'text', labels: { de: '<b>Geburtsdatum</b>' }, wikiLink: { labelTarget: 'Attribut:Geburtsdatum' } } );
  const w = createTestWrapper( PropertyLabel, { property } );
  expect( w.text() ).toBe( '<b>Geburtsdatum</b>' );
  expect( w.find( 'b' ).exists() ).toBe( false );
  expect( w.findComponent( { name: 'WikiPageLink' } ).props( 'title' ) ).toBe( 'Attribut:Geburtsdatum' );
 } );
} );
