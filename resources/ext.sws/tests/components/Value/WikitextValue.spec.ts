/* eslint-disable @typescript-eslint/explicit-function-return-type */
import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';
import { CdxTextArea } from '@wikimedia/codex';
import { createTestWrapper, createI18nMock } from '../../VueTestHelpers';
import { WikitextContextKey } from '@/components/Value/ValueDisplayContract';
import WikitextDisplay from '@/components/Value/WikitextDisplay.vue';
import WikitextInput from '@/components/Value/WikitextInput.vue';
import { createPropertyDefinitionFromJson } from '@/domain/PropertyDefinition';
import type { TextProperty } from '@/domain/propertyTypes/Text';
import { newWikitextValue } from '@/domain/propertyTypes/Wikitext';
import { Neo } from '@/Neo';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
const get = vi.fn();
const using = vi.fn().mockResolvedValue( undefined );
function batch( item: unknown ) { return { swsrenderwikitext: { entries: { [ JSON.stringify( [ 's11111111111111', 'Anmerkung', 'value' ] ) ]: item } } }; }
const property = () => createPropertyDefinitionFromJson( 'Anmerkung', { type: 'wikitextValue', multiple: true } ) as TextProperty;
function display( text = '[[Brünn]]' ) {
 return mount( WikitextDisplay, { props: { property: property(), value: newWikitextValue( text ) }, global: {
  provide: { [ WikitextContextKey as symbol ]: ref( { pageId: 123, subject: 's11111111111111' } ) },
  mocks: { $i18n: createI18nMock() },
 } } );
}
beforeEach( () => { get.mockReset(); using.mockClear(); vi.stubGlobal( 'mw', { Api: class { get = get; }, loader: { using }, msg: ( key: string ) => key } ); } );
afterEach( () => vi.unstubAllGlobals() );
describe( 'editable subject Wikitext', () => {
 it( 'registers independently and preserves indentation and newlines', () => {
  const registry = Neo.getInstance().getPropertyTypeRegistry();
  expect( registry.getType( 'wikitextValue' ).getValueType() ).toBe( 'string' );
  expect( property().type ).toBe( 'wikitextValue' );
  expect( Neo.getInstance().getValueDeserializer().deserialize( [ '  [[Brünn]]\n' ], 'wikitextValue' ) ).toEqual( newWikitextValue( '  [[Brünn]]\n' ) );
 } );
 it( 'edits source without an execution switch', async () => {
  const w = createTestWrapper( WikitextInput, { property: property(), modelValue: undefined } );
  w.findComponent( CdxTextArea ).vm.$emit( 'update:modelValue', '  [[Brünn]]\n' );
  await flushPromises();
  expect( ( w.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newWikitextValue( '  [[Brünn]]\n' ) );
 } );
 it( 'shows parser HTML for the saved field using its host identity', async () => {
  get.mockResolvedValue( batch( { source: '[[Brünn]]', html: '<p><a href="/wiki/Brünn">Brünn</a></p>', modules: [ 'ext.example' ], styles: [] } ) );
  const w = display(); await flushPromises();
  expect( get ).toHaveBeenCalledWith( { action: 'swsrenderwikitext', pageid: 123, all: true, format: 'json' } );
  expect( w.get( 'a' ).text() ).toBe( 'Brünn' );
  expect( using ).toHaveBeenCalledWith( [ 'ext.example' ] );
  expect( w.find( 'pre' ).exists() ).toBe( false );
 } );
 it( 'does not render an answer for a different saved value', async () => {
  get.mockResolvedValue( batch( { source: 'different', html: '<b>Wrong result</b>' } ) );
  const w = display(); await flushPromises();
  expect( w.find( 'b' ).exists() ).toBe( false );
  expect( w.get( 'pre' ).text() ).toBe( '[[Brünn]]' );
  expect( w.text() ).toContain( 'sws-wikitext-render-error' );
 } );
 it( 'keeps failed source escaped and visible', async () => {
  get.mockRejectedValue( new Error( 'unavailable' ) );
  const w = display( '<script>source</script>' ); await flushPromises();
  expect( w.find( 'script' ).exists() ).toBe( false );
  expect( w.get( 'pre' ).text() ).toBe( '<script>source</script>' );
 } );
 it( 'does not display a page batch whose source no longer matches the edited field', async () => {
  let finish!: ( value: unknown ) => void;
  get.mockImplementationOnce( () => new Promise( resolve => { finish = resolve; } ) );
  const w = display(); await flushPromises();
  await w.setProps( { value: newWikitextValue( '[[Brno]]' ) } );
  finish( batch( { source: '[[Brünn]]', html: '<b>Old</b>' } ) ); await flushPromises();
  expect( w.find( 'b' ).exists() ).toBe( false );
  expect( w.text() ).toContain( 'sws-wikitext-render-error' );
  expect( w.get( 'pre' ).text() ).toBe( '[[Brno]]' );
 } );
 it( 'does not send unsaved or context-free text to the parser', async () => {
  const w = createTestWrapper( WikitextDisplay, { property: property(), value: newWikitextValue( '[[Brünn]]' ) } );
  await flushPromises(); expect( get ).not.toHaveBeenCalled(); expect( w.get( 'pre' ).text() ).toBe( '[[Brünn]]' );
 } );
} );
