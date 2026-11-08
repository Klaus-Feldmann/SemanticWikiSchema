import { describe, expect, it } from 'vitest';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { Neo } from '@/Neo';
import { SWSExtension } from '@/SWSExtension';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, ValueType } from '@/domain/Value';
describe( 'image type registration and persistence', () => {
	it( 'is registered as a distinct image field with input, display and attributes components', () => {
		expect( Neo.getInstance().getPropertyTypeRegistry().getType( 'image' ).getValueType() ).toBe( ValueType.String );
		const components = SWSExtension.getInstance().getTypeSpecificComponentRegistry();
		expect( components.getLabel( 'image' ) ).toBe( 'sws-property-type-image' );
		expect( components.getValueDisplayComponent( 'image' ) ).toBeDefined();
		expect( components.getValueEditingComponent( 'image' ) ).toBeDefined();
	} );
	it( 'round-trips image settings without losing optional P or label configuration', () => {
		const definition = { type: 'image', multiple: true, uniqueItems: true,
			wikiLink: { label: true, labelTarget: 'Konzept:Bild' }, externalProperty: { source: 'wikidata', id: 'P18' } };
		const schema = new SchemaDeserializer().deserialize( 'Person', { propertyDefinitions: { Bild: definition } } );
		const data = new SchemaSerializer().schemaToJson( schema ) as { propertyDefinitions: { Bild: typeof definition } };
		expect( data.propertyDefinitions.Bild ).toMatchObject( definition );
	} );
	it( 'deserializes repository-qualified statements as own values without remote rewriting', () => {
		const subject = Neo.getInstance().getSubjectDeserializer().deserialize( { id: 's1aaaaaaaaaaaa1', schema: 'Person', pageId: 1152, pageTitle: 'Person',
			statements: { Bild: { propertyType: 'image', value: [ 'commons:Example.jpg', 'local:Example.jpg' ] } } } );
		expect( subject.getStatementValue( new PropertyName( 'Bild' ) ) ).toEqual( newStringValue( 'commons:Example.jpg', 'local:Example.jpg' ) );
		expect( subject.getNamesOfNonEmptyProperties().map( ( name ) => name.toString() ) ).toEqual( [ 'Bild' ] );
	} );
} );
