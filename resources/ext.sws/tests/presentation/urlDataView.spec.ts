import { afterEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import { newSchema, newSubject } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newUrlProperty } from '@/domain/propertyTypes/Url';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newUrlValue } from '@/domain/Value';
import { createTestWrapper } from '../VueTestHelpers';
vi.mock( '@/components/SubjectsManager/ExternalIdentityDisplay.vue', () => ( { default: { template: '<span />' } } ) );
vi.mock( '@/composables/useExternalStatements', async () => {
	const { ref } = await import( 'vue' );
	return { useExternalStatements: () => ( { fields: ref( new Map() ), loading: ref( false ), failed: ref( false ) } ) };
} );
afterEach( () => vi.unstubAllGlobals() );
describe( 'Data view URL rows', () => {
	it( 'uses caption links on the left and counts a multi-source field once', () => {
		vi.stubGlobal( 'mw', { util: { getUrl: ( title: string ) => '/wiki/' + encodeURIComponent( title ) } } );
		setActivePinia( createPinia() );
		const schema = newSchema( { title: 'Person', properties: new PropertyDefinitionList( [ newUrlProperty( { name: 'Quellenangabe', multiple: true } ) ] ) } );
		useSchemaStore().setSchema( 'Person', schema );
		const subject = newSubject( { schemaName: 'Person', statements: new StatementList( [ new Statement( new PropertyName( 'Quellenangabe' ), 'url', newUrlValue( [
			{ url: 'https://de.wikipedia.org/wiki/Regiomontanus', label: 'Regiomontanus', caption: 'Wikipedia (de)', captionPage: 'Vermerk:Wikipedia' },
			{ url: 'https://en.wikipedia.org/wiki/Regiomontanus', label: 'Regiomontanus', caption: 'Wikipedia (en)', captionPage: 'Vermerk:Wikipedia' },
		] ) ) ] ) } );
		const w = createTestWrapper( SubjectStatementsView, { subject } );
		expect( w.findAll( 'dt' ).map( ( el ) => el.text() ) ).toEqual( [ 'Wikipedia (de)', 'Wikipedia (en)' ] );
		expect( w.findAll( 'dd' ).map( ( el ) => el.text() ) ).toEqual( [ 'Regiomontanus', 'Regiomontanus' ] );
		expect( w.get( 'dt a' ).attributes( 'href' ) ).toBe( '/wiki/Vermerk%3AWikipedia' );
		expect( w.emitted( 'count-change' ) ).toContainEqual( [ 1 ] );
		expect( subject.getNamesOfNonEmptyProperties().map( ( name ) => name.toString() ) ).toContain( 'Quellenangabe' );
	} );
} );
