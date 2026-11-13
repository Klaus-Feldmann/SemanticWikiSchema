import { beforeEach, describe, expect, it, vi, onTestFinished } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { setupMwMock, createI18nMock } from '../VueTestHelpers';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';
import { StatementDeserializer } from '@/persistence/StatementDeserializer';
import { RestSchemaRepository } from '@/persistence/RestSchemaRepository';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import { schemaReferenceName, schemaReferenceText, isLocalSchemaReference } from '@/domain/SchemaReference';
import { schemaMenu } from '@/presentation/schemaMenu';
import { StatementList } from '@/domain/StatementList';
import { PageSaver } from '@/persistence/PageSaver';
import { SWSExtension } from '@/SWSExtension';
import IdentityEditor from '@/components/common/IdentityEditor.vue';
import { useSchemaStore } from '@/stores/SchemaStore';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';

const terms = { identityLanguage: 'de', labels: { de: 'Person', en: 'Person' }, descriptions: { de: 'Ein Mensch', en: 'A human' } };
const schemaJson = { formatVersion: 2, id: 'S1', ...terms, propertyDefinitions: {} };
const schemaReader = new SchemaDeserializer();
function reader(): SubjectDeserializer {
	return new SubjectDeserializer( new StatementDeserializer( new ValueDeserializer( SWSExtension.getInstance().getPropertyTypeRegistry() ) ) );
}
const entityJson = { id: 'E7', schema: { id: 'S1' }, label: 'Person', displayName: 'Person', displayNameIsGenerated: false, statements: {}, pageId: 7, pageTitle: 'Test', ...terms };

beforeEach( () => {
	setupMwMock( { functions: [ 'config', 'msg', 'message', 'util' ], config: { wgUserLanguage: 'en', wgContentLanguage: 'de' } } );
	setActivePinia( createPinia() );
} );

describe( 'D3 stable identities over the actual UI adapters', () => {
	it( 'accepts E IDs while retaining legacy IDs and rejects invalid numbers', () => {
		for ( const id of [ 'E1', 'E999999999999999999', 's11111111111111' ] ) {
			expect( new SubjectId( id ).text ).toBe( id );
		}
		for ( const id of [ 'E0', 'E01', 'E-1', 'E1000000000000000000' ] ) {
			expect( SubjectId.isValid( id ) ).toBe( false );
		}
	} );
	it( 'keeps typed schema IDs distinct from legacy names and retains source-qualified IDs', () => {
		expect( schemaReferenceName( { id: 'S1' } ) ).toBe( 'S1' );
		expect( schemaReferenceText( { source: 'remote', id: 'S1' } ) ).toBe( 'remote:S1' );
		expect( isLocalSchemaReference( { id: 'S1' } ) ).toBe( true );
		expect( isLocalSchemaReference( { source: 'remote', id: 'S1' } ) ).toBe( false );
	} );
	it( 'round trips schema identity when adding, removing and reordering properties', () => {
		const schema = schemaReader.deserialize( 'S1', schemaJson );
		const changed = schema.withDescription( 'Forschungsdaten' ).withSubjectsAreDependent( true )
			.withPropertyDefinitions( schema.getPropertyDefinitions() ).withReorderedPropertyDefinitions( [] );
		const json = new SchemaSerializer().schemaToJson( changed );
		expect( json.id ).toBe( 'S1' );
		expect( json.formatVersion ).toBe( 2 );
		expect( json.labels ).toEqual( terms.labels );
		expect( json.descriptions ).toEqual( { de: 'Forschungsdaten', en: 'A human' } );
		expect( changed.getName() ).toBe( 'S1' );
	} );
	it( 'duplicates as a fresh schema without retaining the old identity', () => {
		const draft = schemaReader.deserialize( 'S1', schemaJson ).asNew( 'Person' );
		expect( draft.getId() ).toBeNull();
		expect( draft.getIdentity()?.labels.de ).toBe( 'Person' );
	} );
	it( 'shows equal labels with distinct S IDs and searches translated labels and descriptions', () => {
		const summaries = [ 'S1', 'S2' ].map( ( id ) => ( { name: id, id, ...terms, propertyCount: 0, subjectsAreDependent: false, description: 'Ein Mensch' } ) );
		const menu = schemaMenu( summaries, 'human', null );
		expect( menu.items.map( ( item ) => item.value ) ).toEqual( [ 'S1', 'S2' ] );
		expect( menu.items.map( ( item ) => item.label ) ).toEqual( [ 'Person (S1)', 'Person (S2)' ] );
	} );
	it( 'reads and preserves entity metadata and typed schema refs through ordinary edits', () => {
		const subject = reader().deserialize( entityJson );
		const edited = subject.withLabel( 'Mensch' ).withStatements( new StatementList( [] ) );
		expect( edited.getIdentity()?.labels ).toEqual( { de: 'Mensch', en: 'Person' } );
		expect( edited.getIdentity()?.descriptions ).toEqual( terms.descriptions );
		expect( edited.getSchemaReference() ).toEqual( { id: 'S1' } );
		expect( subject.getIdentity()?.labels.de ).toBe( 'Person' );
	} );
	it( 'encodes label search as a URL component rather than interpreting its slash or fragment', async () => {
		const get = vi.fn().mockResolvedValue( new Response( '[]' ) );
		const repository = new RestSchemaRepository( '/rest.php', { get } as any, new SchemaSerializer(), schemaReader, {} as PageSaver );
		await repository.getSchemaNames( 'Person/Forschung #1' );
		expect( get ).toHaveBeenCalledWith( '/rest.php/sws/v0/schema-names/Person%2FForschung%20%231' );
	} );

	it( 'posts schema creation to the allocating endpoint with a stable retry key', async () => {
		const post = vi.fn().mockResolvedValue( new Response( JSON.stringify( { schema: schemaJson } ), { status: 201 } ) );
		const repository = new RestSchemaRepository( '/rest.php', { post } as any, new SchemaSerializer(), schemaReader, {} as PageSaver );
		const draft = schemaReader.deserialize( 'S1', schemaJson ).asNew( 'Person' );
		const created = await repository.createSchema( draft, 'Test', 'one-operation' );
		expect( post ).toHaveBeenCalledWith( '/rest.php/sws/v0/schema', expect.objectContaining( { requestKey: 'one-operation', schema: expect.objectContaining( { labels: { de: 'Person' } } ) } ) );
		expect( created.getId() ).toBe( 'S1' );
		expect( created.getName() ).toBe( 'S1' );
	} );
	it( 'passes own terms through both page-bound and standalone entity creation', async () => {
		const post = vi.fn().mockImplementation( async () => new Response( JSON.stringify( { subject: entityJson, pageId: 7, pageTitle: 'Test' } ), { status: 201 } ) );
		const repository = new RestSubjectRepository( '/rest.php', { post } as any, reader(), schemaReader );
		await repository.createMainSubject( 7, 'Person', 'S1', new StatementList( [] ), [], 'Test', new SubjectId( 'E7' ), terms );
		expect( post.mock.calls[ 0 ][ 1 ].labels ).toEqual( terms.labels );
		await repository.createSubjectPage( 'Person', 'S1', new StatementList( [] ), [], 'Test', undefined, new SubjectId( 'E7' ), terms );
		expect( post.mock.calls[ 1 ][ 1 ].descriptions ).toEqual( terms.descriptions );
	} );

	it( 'sends all own terms on update and dependent entity metadata as explicit refs', async () => {
		const put = vi.fn().mockResolvedValue( new Response( JSON.stringify( { subject: entityJson } ) ) );
		const repository = new RestSubjectRepository( '/rest.php', { put } as any, reader(), schemaReader );
		const entity = reader().deserialize( entityJson );
		await repository.updateSubject( entity.getId(), entity.getLabel(), entity.getStatements(), [ entity ], 'Test', entity.getIdentity() );
		const body = put.mock.calls[ 0 ][ 1 ];
		expect( body.labels ).toEqual( terms.labels );
		expect( body.descriptions ).toEqual( terms.descriptions );
		expect( body.dependentSubjects[ 0 ].schema ).toEqual( { id: 'S1' } );
		expect( body.dependentSubjects[ 0 ].identityLanguage ).toBe( 'de' );
	} );
	it( 'shares one label lookup for badges on a summary-only page', async () => {
		const getSchemaSummaries = vi.fn().mockResolvedValue( { schemas: [ 'S1', 'S2' ].map( ( id ) => ( { name: id, id, ...terms, description: '', propertyCount: 0, subjectsAreDependent: false } ) ), nextCursor: null } );
		const spy = vi.spyOn( SWSExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( { getSchemaSummaries } as any );
		onTestFinished( () => spy.mockRestore() );
		const first = mount( SchemaNameDisplay, { props: { schemaName: 'S1' } } );
		const second = mount( SchemaNameDisplay, { props: { schemaName: 'S2' } } );
		await flushPromises();
		expect( getSchemaSummaries ).toHaveBeenCalledTimes( 1 );
		expect( first.get( '.ext-sws-schema-name__text' ).text() ).toBe( 'Person' );
		expect( second.get( '.ext-sws-schema-name__text' ).text() ).toBe( 'Person' );
		expect( second.get( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Schema:S2' );
	} );
	it( 'keeps a stable ID usable when the label catalog is unavailable', async () => {
		const spy = vi.spyOn( SWSExtension.getInstance(), 'getSchemaRepository' ).mockReturnValue( { getSchemaSummaries: vi.fn().mockRejectedValue( new Error( 'Unavailable' ) ) } as any );
		onTestFinished( () => spy.mockRestore() );
		const wrapper = mount( SchemaNameDisplay, { props: { schemaName: 'S1' } } );
		await flushPromises();
		expect( wrapper.get( '.ext-sws-schema-name__text' ).text() ).toBe( 'S1' );
		expect( wrapper.get( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Schema:S1' );
	} );

	it( 'displays the label while linking to the stable schema page, and follows a label edit', async () => {
		const store = useSchemaStore();
		store.setSchema( 'S1', schemaReader.deserialize( 'S1', schemaJson ) );
		const wrapper = mount( SchemaNameDisplay, { props: { schemaName: 'S1' } } );
		expect( wrapper.find( 'a' ).attributes( 'href' ) ).toBe( '/wiki/Schema:S1' );
		expect( wrapper.get( '.ext-sws-schema-name__text' ).text() ).toBe( 'Person' );
		store.setSchema( 'S1', schemaReader.deserialize( 'S1', { ...schemaJson, labels: { de: 'Mensch', en: 'Human' } } ) );
		await wrapper.vm.$nextTick();
		expect( wrapper.get( '.ext-sws-schema-name__text' ).text() ).toBe( 'Human' );
	} );
	it( 'edits one translation without discarding the other language or descriptions', async () => {
		const wrapper = mount( IdentityEditor, { props: { modelValue: structuredClone( terms ), id: 'E7' }, global: { mocks: { $i18n: createI18nMock() } } } );
		const inputs = wrapper.findAll( '.ext-sws-identity-editor__row input' );
		await inputs[ 0 ].setValue( 'Mensch' );
		const emitted = wrapper.emitted( 'update:modelValue' )![ 0 ][ 0 ] as typeof terms;
		expect( emitted.labels ).toEqual( { de: 'Mensch', en: 'Person' } );
		expect( emitted.descriptions ).toEqual( terms.descriptions );
		expect( terms.labels.de ).toBe( 'Person' );
	} );
	it( 'never executes markup entered in own labels', () => {
		const wrapper = mount( IdentityEditor, { props: { modelValue: { ...terms, labels: { de: '<img src=x onerror=alert(1)>' } } }, global: { mocks: { $i18n: createI18nMock() } } } );
		expect( wrapper.find( 'img' ).exists() ).toBe( false );
		expect( wrapper.find( 'input' ).element.value ).toBe( '<img src=x onerror=alert(1)>' );
	} );
} );
