import { describe, expect, it } from 'vitest';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import { SchemaSerializer } from '@/persistence/SchemaSerializer';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import { TextType } from '@/domain/propertyTypes/Text';
import { SelectType } from '@/domain/propertyTypes/Select';

// Exercise the normal schema read/write path: a label must never replace the stable ID.
describe( 'property source schema persistence', () => {
	for ( const source of [ 'wikidata', 'wikibase-local' ] ) {
		it( `round-trips a source-qualified ID for ${ source }`, () => {
			const input = { description: 'Test', propertyDefinitions: { Test: { type: 'text', externalProperty: { source, id: 'P31' } } } };
			const schema = new SchemaDeserializer().deserialize( 'Person', input );
			const output = new SchemaSerializer().schemaToJson( schema ) as typeof input;
			expect( output.propertyDefinitions.Test.externalProperty ).toEqual( { source, id: 'P31' } );
		} );
	}
	it( 'keeps source metadata when changing a property type', () => {
		const registry = new PropertyTypeRegistry();
		registry.registerType( new TextType() );
		registry.registerType( new SelectType() );
		const schema = new SchemaDeserializer().deserialize( 'Person', { propertyDefinitions: {
			Test: { type: 'text', externalProperty: { source: 'wikidata', id: 'P31' } },
		} } );
		const field = [ ...schema.getPropertyDefinitions() ][ 0 ];
		expect( registry.getType( 'select' ).createPropertyDefinition( field ).externalProperty ).toEqual( { source: 'wikidata', id: 'P31' } );
	} );
	for ( const externalTarget of [ 'source', 'local' ] ) {
		it( `preserves the ${ externalTarget } value link target on read/write and type changes`, () => {
			const input = { propertyDefinitions: { Test: { type: 'text', externalProperty: { source: 'wikidata', id: 'P31' },
				wikiLink: { namespace: 'Werk', externalTarget } } } };
			const schema = new SchemaDeserializer().deserialize( 'Person', input );
			const output = new SchemaSerializer().schemaToJson( schema ) as typeof input;
			expect( output.propertyDefinitions.Test.wikiLink ).toEqual( input.propertyDefinitions.Test.wikiLink );
			const field = [ ...schema.getPropertyDefinitions() ][ 0 ];
			expect( new SelectType().createPropertyDefinition( field ).wikiLink ).toEqual( input.propertyDefinitions.Test.wikiLink );
		} );
	}

} );
