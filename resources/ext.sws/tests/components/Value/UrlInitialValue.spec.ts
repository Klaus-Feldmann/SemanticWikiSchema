import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { CdxTextInput } from '@wikimedia/codex';
import UrlInput from '@/components/Value/UrlInput.vue';
import { newUrlValue, type UrlValue } from '@/domain/Value';
import { newUrlProperty } from '@/domain/propertyTypes/Url';
import { createTestWrapper } from '../../VueTestHelpers';
import type { ValueInputExposes } from '@/components/Value/ValueInputContract';
const preset = () => newUrlValue( [ { url: '', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia (de)' } ], true );
beforeEach( () => vi.stubGlobal( 'mw', { msg: ( key: string ) => key, message: ( key: string ) => ( { text: () => key } ), util: { getUrl: ( title: string ) => '/wiki/' + encodeURIComponent( title ) } } ) );
afterEach( () => vi.unstubAllGlobals() );
describe( 'URL preset context', () => {
 it( 'allows incomplete schema presets and emits metadata without an address', async () => {
  const w = createTestWrapper( UrlInput, { initialValueMode: true, modelValue: preset(), property: newUrlProperty( { name: 'Quelle' } ) } );
  w.findAllComponents( CdxTextInput )[ 2 ].vm.$emit( 'update:model-value', 'Wikipedia (en)' );
  await w.vm.$nextTick();
  const api = w.vm as unknown as ValueInputExposes;
  expect( api.unparseableInputMessage!() ).toBeNull();
  expect( ( api.getCurrentValue() as UrlValue ).parts[ 0 ].caption ).toBe( 'Wikipedia (en)' );
 } );
 it( 'does not save untouched optional incomplete presets, but preserves visible metadata', async () => {
  const w = createTestWrapper( UrlInput, { modelValue: preset(), property: newUrlProperty( { name: 'Quelle' } ) } );
  const api = w.vm as unknown as ValueInputExposes;
  expect( api.getCurrentValue() ).toBeUndefined();
  expect( api.unparseableInputMessage!() ).toBeNull();
  expect( w.findAllComponents( CdxTextInput )[ 2 ].props( 'modelValue' ) ).toBe( 'Wikipedia (de)' );
  w.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:model-value', 'https://de.wikipedia.org/wiki/Platon' );
  await w.vm.$nextTick();
  expect( ( api.getCurrentValue() as UrlValue ).parts[ 0 ].caption ).toBe( 'Wikipedia (de)' );
 } );
 it( 'rejects edited incomplete subject entries and required incomplete presets', async () => {
  const w = createTestWrapper( UrlInput, { modelValue: preset(), property: newUrlProperty( { name: 'Quelle' } ) } );
  w.findAllComponents( CdxTextInput )[ 2 ].vm.$emit( 'update:model-value', 'Geändert' );
  await w.vm.$nextTick();
  expect( ( w.vm as unknown as ValueInputExposes ).unparseableInputMessage!() ).toBe( 'sws-url-needs-address' );
  const required = createTestWrapper( UrlInput, { modelValue: preset(), property: newUrlProperty( { name: 'Quelle', required: true } ) } );
  expect( ( required.vm as unknown as ValueInputExposes ).unparseableInputMessage!() ).toBe( 'sws-url-needs-address' );
 } );
 it( 'continues to reject malformed nonempty URLs in schema mode', async () => {
  const w = createTestWrapper( UrlInput, { initialValueMode: true, modelValue: preset(), property: newUrlProperty( { name: 'Quelle' } ) } );
  w.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:model-value', 'not a url' );
  await w.vm.$nextTick();
  expect( ( w.vm as unknown as ValueInputExposes ).unparseableInputMessage!() ).toBe( 'sws-url-invalid-address' );
 } );
} );

describe( 'Multiple URL presets', () => {
 it( 'saves a completed row while leaving a second untouched incomplete row out', async () => {
  const w = createTestWrapper( UrlInput, { modelValue: newUrlValue( [
   { url: '', caption: 'Wikipedia (de)' }, { url: '', caption: 'Wikipedia (en)' },
  ], true ), property: newUrlProperty( { name: 'Quelle', multiple: true } ) } );
  w.findAllComponents( CdxTextInput )[ 0 ].vm.$emit( 'update:model-value', 'https://de.wikipedia.org/wiki/Platon' );
  await w.vm.$nextTick();
  expect( ( ( w.vm as unknown as ValueInputExposes ).getCurrentValue() as UrlValue ).parts ).toEqual( [
   { url: 'https://de.wikipedia.org/wiki/Platon', caption: 'Wikipedia (de)' },
  ] );
 } );
} );
