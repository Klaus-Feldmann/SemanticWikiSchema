import { afterEach, describe, expect, it, vi } from 'vitest';
import { loadSavedWikitext } from '@/persistence/WikitextBatch';
afterEach( () => vi.unstubAllGlobals() );
describe( 'page-wide saved wikitext batching', () => {
 it( 'shares one request for two fields and a note on the same page', async () => {
  const entries = Object.fromEntries( [ [ 'A', 'value' ], [ 'B', 'value' ], [ 'A', 'note' ] ].map( ( [ field, kind ] ) => [ JSON.stringify( [ 's11111111111111', field, kind ] ), { source: field, html: '<p>' + field + '</p>' } ] ) );
  const get = vi.fn().mockResolvedValue( { swsrenderwikitext: { entries } } );
  vi.stubGlobal( 'mw', { Api: class { public get = get; } } );
  const results = await Promise.all( [ loadSavedWikitext( 456, 's11111111111111', 'A' ), loadSavedWikitext( 456, 's11111111111111', 'B' ), loadSavedWikitext( 456, 's11111111111111', 'A', true ) ] );
  expect( get ).toHaveBeenCalledTimes( 1 ); expect( results ).toHaveLength( 3 );
  expect( get ).toHaveBeenCalledWith( { action: 'swsrenderwikitext', pageid: 456, all: true, format: 'json' } );
  await loadSavedWikitext( 456, 's11111111111111', 'A' ); expect( get ).toHaveBeenCalledTimes( 2 );
 } );
 it( 'clears a failed request so that a later retry can succeed', async () => {
  const key = JSON.stringify( [ 's11111111111111', 'A', 'note' ] );
  const get = vi.fn().mockRejectedValueOnce( new Error( 'offline' ) ).mockResolvedValueOnce( { swsrenderwikitext: { entries: { [ key ]: { source: 'x', html: '<p>x</p>' } } } } );
  vi.stubGlobal( 'mw', { Api: class { public get = get; } } );
  await expect( loadSavedWikitext( 789, 's11111111111111', 'A', true ) ).rejects.toThrow( 'offline' );
  await expect( loadSavedWikitext( 789, 's11111111111111', 'A', true ) ).resolves.toMatchObject( { source: 'x' } );
 } );
} );
