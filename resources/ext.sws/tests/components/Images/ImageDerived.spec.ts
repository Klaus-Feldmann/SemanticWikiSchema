import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { ImageType } from '@/domain/propertyTypes/Image';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { newSchema, newSubject } from '@/TestHelpers';
import { resolveImage } from '@/persistence/ImageLookup';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn() } ) );
const sid = 's1aaaaaaaaaaaa1';
const reference = 'commons:Goethe (Stieler 1828).jpg';
describe( 'read-only image claims in the normal data view', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'util', 'config' ] } );
		mw.util.wikiScript = () => '/w/api.php';
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
		vi.mocked( resolveImage ).mockResolvedValue( { reference, repository: 'commons', filename: 'Goethe (Stieler 1828).jpg',
			descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Goethe_(Stieler_1828).jpg', thumbnailUrl: 'https://thumb.wikimedia.org/goethe.jpg', width: 122, height: 150, mime: 'image/jpeg' } );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	for ( const source of [ 'wikidata', 'wikibase-local' ] as const ) {
		it( `renders ${ source } image claim without writing an own image value`, async () => {
			const definition = new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image', externalProperty: { source, id: 'P18' } }, { imageSource: source } );
			useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ definition ] ) } ) );
			const subject = newSubject( { id: sid, schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ) } );
			const propertyUrl = source === 'wikidata' ? 'https://www.wikidata.org/wiki/Property:P18' : window.location.origin + '/wiki/Property:P18';
			vi.stubGlobal( 'fetch', vi.fn().mockImplementation( async () => ( { ok: true, json: async () => ( { swsbasevalues: {
				version: 1, scope: 'current', pageId: 1152, mainSubjectId: sid, fields: [ { name: 'Bild', source, propertyId: 'P18', propertyLabel: 'Bild', propertyUrl, status: 'resolved',
					values: [ { kind: 'image', reference, repository: 'commons', filename: 'Goethe (Stieler 1828).jpg' } ] } ],
			} } ) } ) ) );
			const w = createTestWrapper( SubjectStatementsView, { subject } );
			await flushPromises();
			expect( w.get( 'img' ).attributes( 'src' ) ).toBe( 'https://thumb.wikimedia.org/goethe.jpg' );
			expect( w.get( `a[href="${ propertyUrl }"]` ).text() ).toBe( 'Bild' );
			expect( w.findAll( '.ext-sws-derived-image' ) ).toHaveLength( 1 );
			expect( subject.getNamesOfNonEmptyProperties() ).toEqual( [] );
			w.unmount();
		} );
	}
} );
