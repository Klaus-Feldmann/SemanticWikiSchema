import { afterEach, describe, it, expect, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import { newSchema, newSubject } from '@/TestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newStringValue } from '@/domain/Value';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { useSchemaStore } from '@/stores/SchemaStore';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';
const sid = 's1aaaaaaaaaaaa1';
async function show( image: boolean ): Promise<VueWrapper> {
	const pinia = createPinia();
	setActivePinia( pinia );
	setupMwMock( { functions: [ 'config', 'util' ], config: { wgUserLanguage: 'de' } } );
	mw.util.wikiScript = () => '/w/api.php';
	const property = { ...newTextProperty( { name: 'Bild' } ), type: 'image' };
	useSchemaStore().setSchema( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ property ] ) } ) );
	const subject = newSubject( { id: sid, schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 42, 'Person' ),
		statements: new StatementList( image ? [ new Statement( property.name, 'image', newStringValue( 'local:Portrait.jpg' ) ) ] : [] ) } );
	vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { swsbaseidentity: {
		version: 2, scope: 'current', pageId: 42, mainSubjectId: sid,
		identities: { [ sid ]: { wikidata: { status: 'resolved', id: 'Q5', url: 'https://www.wikidata.org/wiki/Q5' }, 'wikibase-local': { status: 'missing', id: null, url: null } } },
		descriptions: [ { text: 'Beschreibung', language: 'de', sources: [ { source: 'wikidata', id: 'Q5', url: 'https://www.wikidata.org/wiki/Q5' } ] } ],
	} } ) } ) );
	const wrapper = mount( SubjectStatementsView, { props: { subject }, global: { plugins: [ pinia ], mocks: { $i18n: createI18nMock() },
		stubs: { StatementDisplay: { template: '<div class="test-image">Bild</div>' } } } } );
	await flushPromises();
	return wrapper;
}
afterEach( () => vi.unstubAllGlobals() );
describe( 'description placement', () => {
	it( 'places description next to image without counting it as an extra field', async () => {
		const w = await show( true );
		expect( w.findAll( '.ext-sws-entity-descriptions' ) ).toHaveLength( 1 );
		expect( w.find( '.ext-sws-subject-statements__value--with-description .ext-sws-entity-descriptions' ).exists() ).toBe( true );
		expect( w.emitted( 'count-change' )?.slice( -1 )[ 0 ] ).toEqual( [ 1 ] );
		w.unmount();
	} );
	it( 'places description below identities when there is no image', async () => {
		const w = await show( false );
		expect( w.find( '.ext-sws-entity-descriptions' ).exists() ).toBe( true );
		expect( w.find( '.ext-sws-subject-statements__value--with-description' ).exists() ).toBe( false );
		expect( w.get( '.ext-sws-entity-descriptions' ).element.previousElementSibling?.className ).toBe( 'ext-sws-external-identity' );
		w.unmount();
	} );
} );
