import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import ExternalIdentityDisplay from '@/components/SubjectsManager/ExternalIdentityDisplay.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';
import { newSubject } from '@/TestHelpers';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
const id = 's1aaaaaaaaaaaa1';
const subject = newSubject( { id, pageIdentifiers: new PageIdentifiers( 1152, 'Nikolaus von Kues' ) } );
function show( config: Record<string, unknown> = {} ): VueWrapper {
	setupMwMock( { functions: [ 'config', 'util' ], config } );
	mw.util.wikiScript = () => '/w/api.php';
	return mount( ExternalIdentityDisplay, { props: { subject }, global: { mocks: { $i18n: createI18nMock() } } } );
}
function resolved( idText: string, url: string ): Record<string, unknown> {
	return { status: 'resolved', id: idText, url };
}
function response( overrides: Record<string, unknown> = {}, main = id ): void {
	vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( {
		swsbaseidentity: { version: 2, pageId: 1152, scope: 'current', mainSubjectId: main,
			identities: { [ id ]: {
				wikidata: resolved( 'Q154751', 'https://www.wikidata.org/wiki/Q154751' ),
				'wikibase-local': resolved( 'Q56', 'https://aufeinwort.info/wiki/Qwasi:Q56' ), ...overrides,
			} } },
	} ) } ) );
}
describe( 'automatic source identities without schema fields', () => {
	beforeEach( () => response() );
	afterEach( () => {
		vi.unstubAllGlobals();
		vi.restoreAllMocks();
	} );
	it( 'renders both source IDs next to each other with distinct links', async () => {
		const w = show();
		await flushPromises();
		const links = w.findAll( 'a' );
		expect( links.map( ( a ) => a.text() ) ).toEqual( [ 'Q154751', 'Q56' ] );
		expect( links.map( ( a ) => a.attributes( 'href' ) ) ).toEqual( [
			'https://www.wikidata.org/wiki/Q154751', 'https://aufeinwort.info/wiki/Qwasi:Q56',
		] );
		expect( w.text() ).toContain( ' · ' );
		w.unmount();
	} );
	it( 'keeps the local ID visible when the external association is missing', async () => {
		response( { wikidata: { status: 'missing', id: null, url: null } } );
		const w = show();
		await flushPromises();
		expect( w.findAll( 'a' ).map( ( a ) => a.text() ) ).toEqual( [ 'Q56' ] );
		expect( w.text() ).not.toContain( 'sws-auto-identity-wikidata' );
		expect( w.text() ).not.toContain( ' · ' );
		w.unmount();
	} );
	it( 'reports source errors separately from missing associations', async () => {
		response( { 'wikibase-local': { status: 'error', id: null, url: null } } );
		const w = show();
		await flushPromises();
		expect( w.get( 'a' ).text() ).toBe( 'Q154751' );
		expect( w.text() ).not.toContain( 'sws-auto-identity-wikibase-local' );
		expect( w.text() ).not.toContain( ' · ' );
		w.unmount();
	} );
	it( 'does not assign the page identity to another subject', async () => {
		response( {}, 's2aaaaaaaaaaaa2' );
		const w = show();
		await flushPromises();
		expect( w.find( '.ext-sws-external-identity' ).exists() ).toBe( false );
		w.unmount();
	} );
	it( 'does not fetch current identities for historical views', async () => {
		const w = show( { wgRevisionId: 10, wgCurRevisionId: 11 } );
		await flushPromises();
		expect( fetch ).not.toHaveBeenCalled();
		w.unmount();
	} );
	it( 'rejects invalid links instead of rendering them', async () => {
		// eslint-disable-next-line no-script-url -- Exercise the rejected protocol.
		response( { wikidata: resolved( 'Q154751', 'javascript:alert(1)' ) } );
		const w = show();
		await flushPromises();
		expect( w.find( 'a' ).exists() ).toBe( false );
		expect( w.find( '.ext-sws-external-identity' ).exists() ).toBe( false );
		w.unmount();
	} );
	it( 'shows the same numeric ID separately for each source', async () => {
		response( { wikidata: resolved( 'Q56', 'https://www.wikidata.org/wiki/Q56' ) } );
		const w = show();
		await flushPromises();
		expect( w.findAll( 'a' ) ).toHaveLength( 2 );
		expect( w.findAll( 'a' ).map( ( a ) => a.attributes( 'href' ) )[ 0 ] ).not.toBe(
			w.findAll( 'a' )[ 1 ].attributes( 'href' ),
		);
		w.unmount();
	} );
	it( 'renders no row when both associations are absent', async () => {
		response( { wikidata: { status: 'missing', id: null, url: null },
			'wikibase-local': { status: 'unavailable', id: null, url: null } } );
		const w = show();
		await flushPromises();
		expect( w.find( '.ext-sws-external-identity' ).exists() ).toBe( false );
		w.unmount();
	} );
	it( 'renders no row while loading or after a request failure', async () => {
		vi.stubGlobal( 'fetch', vi.fn().mockRejectedValue( new Error( 'Offline' ) ) );
		const w = show();
		expect( w.find( '.ext-sws-external-identity' ).exists() ).toBe( false );
		await flushPromises();
		expect( w.find( '.ext-sws-external-identity' ).exists() ).toBe( false );
		w.unmount();
	} );

	it( 'requests descriptions in the UI language and emits validated groups', async () => {
		const groups = [ { text: 'Kardinal', language: 'de', sources: [ { source: 'wikidata', id: 'Q154751', url: 'https://www.wikidata.org/wiki/Q154751' } ] } ];
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( {
			swsbaseidentity: { version: 2, pageId: 1152, scope: 'current', mainSubjectId: id,
				identities: { [ id ]: { wikidata: resolved( 'Q154751', 'https://www.wikidata.org/wiki/Q154751' ), 'wikibase-local': { status: 'missing', id: null, url: null } } }, descriptions: groups },
		} ) } ) );
		const w = show( { wgUserLanguage: 'de' } );
		await flushPromises();
		expect( String( vi.mocked( fetch ).mock.calls[ 0 ][ 0 ] ) ).toContain( 'descriptions=1' );
		expect( String( vi.mocked( fetch ).mock.calls[ 0 ][ 0 ] ) ).toContain( 'language=de' );
		expect( w.emitted( 'descriptions' )?.slice( -1 )[ 0 ] ).toEqual( [ groups ] );
		w.unmount();
	} );

	it( 'uses the server-selected page description instead of both Q descriptions', async () => {
		const group = { text: 'Eigene Seite', language: 'de', sources: [ { source: 'page', id: '1152', url: window.location.origin + '/wiki/Nikolaus_von_Kues' } ] };
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( {
			swsbaseidentity: { version: 2, pageId: 1152, scope: 'current', mainSubjectId: id,
				identities: { [ id ]: { wikidata: resolved( 'Q154751', 'https://www.wikidata.org/wiki/Q154751' ), 'wikibase-local': resolved( 'Q56', 'https://aufeinwort.info/wiki/Qwasi:Q56' ) } },
				descriptions: [], displayDescription: group, pageDescription: { url: group.sources[ 0 ].url } },
		} ) } ) );
		const w = show();
		await flushPromises();
		expect( w.emitted( 'descriptions' )?.slice( -1 )[ 0 ] ).toEqual( [ [ group ] ] );
		w.unmount();
	} );

} );
