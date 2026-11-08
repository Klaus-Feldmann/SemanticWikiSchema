import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import WikiLinkEditor from '@/components/SchemaEditor/Property/WikiLinkEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { newSchema, newSubject } from '@/TestHelpers';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { ImageType } from '@/domain/propertyTypes/Image';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { newStringValue } from '@/domain/Value';
import { resolveImage } from '@/persistence/ImageLookup';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn() } ) );
describe( 'own image in the regular data view', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'util', 'config' ] } );
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
		vi.mocked( resolveImage ).mockResolvedValue( { reference: 'commons:Example.jpg', repository: 'commons', filename: 'Example.jpg',
			descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Example.jpg', thumbnailUrl: 'https://upload.wikimedia.org/thumb.jpg', width: 145, height: 150, mime: 'image/jpeg' } );
	} );
	it( 'renders thumbnail via the type registry and keeps the internal label target independent', async () => {
		const definition = new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image',
			wikiLink: { labelTarget: 'Konzept:Bild' } }, {} );
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ definition ] ) } ) );
		const subject = newSubject( { schemaName: 'Person', statements: new StatementList( [ new Statement( new PropertyName( 'Bild' ), 'image', newStringValue( 'commons:Example.jpg' ) ) ] ) } );
		const w = createTestWrapper( SubjectStatementsView, { subject } );
		await flushPromises();
		expect( w.get( 'dt a' ).attributes( 'href' ) ).toBe( '/wiki/Konzept%3ABild' );
		expect( w.get( 'img' ).attributes( 'width' ) ).toBe( '145' );
		expect( w.get( 'dd a' ).attributes( 'href' ) ).toBe( 'https://commons.wikimedia.org/wiki/File:Example.jpg' );
		expect( subject.getStatementValue( new PropertyName( 'Bild' ) ) ).toEqual( newStringValue( 'commons:Example.jpg' ) );
		w.unmount();
	} );
	it( 'offers label navigation but no misleading content-page target for an image click', () => {
		const definition = new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image' }, {} );
		const w = createTestWrapper( WikiLinkEditor, { property: definition } );
		expect( w.text() ).toContain( 'sws-link-label' );
		expect( w.text() ).not.toContain( 'sws-link-values' );
		expect( w.text() ).not.toContain( 'sws-link-namespace' );
		w.unmount();
	} );
} );
