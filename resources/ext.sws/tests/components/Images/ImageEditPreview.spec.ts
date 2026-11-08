import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { computed } from 'vue';
import { mount, flushPromises } from '@vue/test-utils';
import { CdxSelect } from '@wikimedia/codex';
import ImageInput from '@/components/Value/ImageInput.vue';
import { ImageSubjectContext } from '@/components/Value/ImageSubjectContext';
import { ImageType } from '@/domain/propertyTypes/Image';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newSubject } from '@/TestHelpers';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { resolveImage } from '@/persistence/ImageLookup';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers';
vi.mock( '@/persistence/ImageLookup', async ( original ) => ( { ...await original<object>(), resolveImage: vi.fn() } ) );
describe( 'automatic preview in the subject editor', () => {
	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'util', 'message' ], config: { wgSWSProjectName: 'AufEinWort' } } );
		mw.util.wikiScript = () => '/w/api.php';
		vi.mocked( resolveImage ).mockResolvedValue( { reference: 'commons:Goethe.jpg', repository: 'commons', filename: 'Goethe.jpg',
			descriptionUrl: 'https://commons.wikimedia.org/wiki/File:Goethe.jpg', thumbnailUrl: 'https://thumb.wikimedia.org/goethe.jpg', width: 122, height: 150, mime: 'image/jpeg' } );
	} );
	afterEach( () => vi.unstubAllGlobals() );
	it( 'previews its configured claim without copying it into own values, and does not reuse P18 across sources', async () => {
		const subject = newSubject( { id: 's1aaaaaaaaaaaa1', pageIdentifiers: new PageIdentifiers( 1152, 'Person' ) } );
		const property = new ImageType().createPropertyDefinitionFromJson( { ...newTextProperty( { name: 'Bild' } ), type: 'image', externalProperty: { source: 'wikidata', id: 'P18' } }, { imageSource: 'wikidata' } );
		vi.stubGlobal( 'fetch', vi.fn().mockResolvedValue( { ok: true, json: async () => ( { swsbasevalues: {
			version: 1, scope: 'current', pageId: 1152, mainSubjectId: subject.getId().text,
			fields: [ { name: 'Bild', source: 'wikidata', propertyId: 'P18', propertyUrl: 'https://www.wikidata.org/wiki/Property:P18', status: 'resolved',
				values: [ { kind: 'image', reference: 'commons:Goethe.jpg', repository: 'commons', filename: 'Goethe.jpg' } ] } ],
		} } ) } ) );
		const w = mount( ImageInput, { props: { property, modelValue: undefined }, global: {
			provide: { [ ImageSubjectContext as symbol ]: computed( () => subject ) }, mocks: { $i18n: createI18nMock() },
		} } );
		await flushPromises();
		expect( w.get( 'img' ).attributes( 'src' ) ).toBe( 'https://thumb.wikimedia.org/goethe.jpg' );
		expect( w.emitted( 'update:modelValue' ) ).toBeUndefined();
		await w.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'wikibase-local' );
		expect( w.find( 'img' ).exists() ).toBe( false );
		expect( w.text() ).toContain( 'sws-image-configure-source' );
		expect( fetch ).toHaveBeenCalledTimes( 1 );
		w.unmount();
	} );
} );
