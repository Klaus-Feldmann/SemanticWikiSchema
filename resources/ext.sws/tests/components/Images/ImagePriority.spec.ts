import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { CdxSelect, CdxButton } from '@wikimedia/codex';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import ImageInput from '@/components/Value/ImageInput.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { ImageType, type ImageProperty } from '@/domain/propertyTypes/Image';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { newStringValue } from '@/domain/Value';
import { newSchema, newSubject } from '@/TestHelpers';
import { resolveImage } from '@/persistence/ImageLookup';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn() } ) );
function imageProperty(): ImageProperty {
	return new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image', externalProperty: { source: 'wikidata', id: 'P18' } }, { imageSource: 'wikidata' } );
}
describe( 'own images override automatic claims', () => {
	beforeEach( () => {
		setActivePinia( createPinia() );
		setupMwMock( { functions: [ 'config', 'util', 'message' ], config: { wgSWSProjectName: 'AufEinWort' } } );
		mw.util.wikiScript = () => '/w/api.php';
		mw.util.getUrl = ( title ) => '/wiki/' + encodeURIComponent( title! );
		vi.mocked( resolveImage ).mockReset().mockImplementation( async ( ref ) => ( { reference: ref, repository: ref.startsWith( 'local:' ) ? 'local' : 'commons', filename: ref.split( ':' )[ 1 ],
			descriptionUrl: window.location.origin + '/wiki/Datei:Own.jpg', thumbnailUrl: '/images/' + ref.split( ':' )[ 1 ], width: 122, height: 150, mime: 'image/jpeg' } ) );
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { swsbasevalues: {
			version: 1, scope: 'current', pageId: 1152, mainSubjectId: 's1aaaaaaaaaaaa1', fields: [ { name: 'Bild', source: 'wikidata', propertyId: 'P18', status: 'resolved',
				values: [ { kind: 'image', reference: 'commons:Remote.jpg', filename: 'Remote.jpg', repository: 'commons' } ] } ],
		} } ) } ) );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'shows only the own picture and restores P18 after its reference is removed', async () => {
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ imageProperty() ] ) } ) );
		const makeSubject = ( own: boolean ): ReturnType<typeof newSubject> => newSubject( { id: 's1aaaaaaaaaaaa1', schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ),
			statements: new StatementList( own ? [ new Statement( new PropertyName( 'Bild' ), 'image', newStringValue( 'local:Own.jpg' ) ) ] : [] ) } );
		const subject = makeSubject( true );
		const w = createTestWrapper( SubjectStatementsView, { subject } );
		await flushPromises();
		expect( w.findAll( 'img' ) ).toHaveLength( 1 );
		expect( w.get( 'img' ).attributes( 'src' ) ).toBe( '/images/Own.jpg' );
		expect( resolveImage ).not.toHaveBeenCalledWith( 'commons:Remote.jpg' );
		expect( subject.getStatementValue( new PropertyName( 'Bild' ) ) ).toEqual( newStringValue( 'local:Own.jpg' ) );
		await w.setProps( { subject: makeSubject( false ) } );
		await flushPromises();
		expect( w.findAll( 'img' ) ).toHaveLength( 1 );
		expect( w.get( 'img' ).attributes( 'src' ) ).toBe( '/images/Remote.jpg' );
		w.unmount();
	} );
	it( 'keeps own choice in the editor and restores the configured source only after explicit removal', async () => {
		const w = createTestWrapper( ImageInput, { property: imageProperty(), modelValue: newStringValue( 'local:Own.jpg' ) } );
		await flushPromises();
		expect( w.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'local' );
		expect( w.findAll( 'img' ) ).toHaveLength( 1 );
		expect( w.findComponent( CdxSelect ).props( 'menuItems' ).find( ( item ) => item.value === 'wikidata' )?.disabled ).toBe( true );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		await w.findComponent( CdxButton ).trigger( 'click' );
		expect( w.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'wikidata' );
		expect( w.emitted( 'update:modelValue' )?.slice( -1 )[ 0 ] ).toEqual( [ undefined ] );
		w.unmount();
	} );
	it( 'does not silently replace an explicitly chosen unavailable picture with the remote claim', async () => {
		vi.mocked( resolveImage ).mockRejectedValue( new Error( 'offline' ) );
		useSchemaStore().schemas.set( 'Person', newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ imageProperty() ] ) } ) );
		const subject = newSubject( { id: 's1aaaaaaaaaaaa1', schemaName: 'Person', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ), statements: new StatementList( [ new Statement( new PropertyName( 'Bild' ), 'image', newStringValue( 'local:Own.jpg' ) ) ] ) } );
		const w = createTestWrapper( SubjectStatementsView, { subject } );
		await flushPromises();
		expect( w.text() ).toContain( 'Own.jpg' );
		expect( w.text() ).not.toContain( 'Remote.jpg' );
		w.unmount();
	} );
	it( 'updates the displayed source when the parent supplies a newly cleared own value', async () => {
		const w = createTestWrapper( ImageInput, { property: imageProperty(), modelValue: newStringValue( 'local:Own.jpg' ) } );
		expect( w.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'local' );
		await w.setProps( { modelValue: undefined } );
		expect( w.findComponent( CdxSelect ).props( 'selected' ) ).toBe( 'wikidata' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		w.unmount();
	} );

} );
