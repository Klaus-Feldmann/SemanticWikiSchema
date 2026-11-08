import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EntityDescriptions from '@/components/SubjectsManager/EntityDescriptions.vue';
import { readDescriptionGroups } from '@/presentation/entityDescriptions';
import { createI18nMock } from '../../VueTestHelpers';
const source = { source: 'wikidata' as const, id: 'Q5', url: 'https://www.wikidata.org/wiki/Q5' };
const group = { text: 'Mensch', language: 'de', sources: [ source ] };
const identities = { wikidata: { status: 'resolved', id: 'Q5', url: source.url } };
describe( 'Q descriptions', () => {
	it( 'renders description with language and source', () => {
		const w = mount( EntityDescriptions, { props: { groups: [ group ] }, global: { mocks: { $i18n: createI18nMock() } } } );
		expect( w.get( '[lang="de"]' ).text() ).toBe( 'Mensch' );
		expect( w.find( 'small' ).exists() ).toBe( true );
		w.unmount();
	} );
	it( 'renders no empty placeholder', () => {
		const w = mount( EntityDescriptions, { props: { groups: [] } } );
		expect( w.find( '.ext-sws-entity-descriptions' ).exists() ).toBe( false );
		w.unmount();
	} );
	it( 'escapes external text', () => {
		const w = mount( EntityDescriptions, { props: { groups: [ { ...group, text: '<img src=x onerror=bad()>' } ] }, global: { mocks: { $i18n: createI18nMock() } } } );
		expect( w.find( 'img' ).exists() ).toBe( false );
		expect( w.text() ).toContain( '<img' );
		w.unmount();
	} );
	it( 'retains only groups matching validated identities', () => {
		expect( readDescriptionGroups( [ group ], identities ) ).toEqual( [ group ] );
		expect( readDescriptionGroups( [ { ...group, sources: [ { ...source, id: 'Q6' } ] } ], identities ) ).toEqual( [] );
		// eslint-disable-next-line no-script-url -- Verify rejected description source URLs.
		expect( readDescriptionGroups( [ { ...group, sources: [ { ...source, url: 'javascript:bad()' } ] } ], identities ) ).toEqual( [] );
		expect( readDescriptionGroups( null, identities ) ).toEqual( [] );
	} );
	it( 'accepts only the matching local page as description source', () => {
		const page = { id: 42, url: window.location.origin + '/wiki/Person' };
		const local = { ...group, sources: [ { source: 'page', id: '42', url: page.url } ] };
		expect( readDescriptionGroups( [ local ], identities, page ) ).toEqual( [ local ] );
		expect( readDescriptionGroups( [ local ], identities, { ...page, id: 43 } ) ).toEqual( [] );
	} );

} );
