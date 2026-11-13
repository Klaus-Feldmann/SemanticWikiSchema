import { describe, expect, it } from 'vitest';
import { LayoutDeserializer } from '@/persistence/LayoutDeserializer';
import { LayoutSerializer } from '@/persistence/LayoutSerializer';

describe( 'layout identity references', () => {
	it( 'preserves an explicit schema ID through reading and saving', () => {
		const layout = new LayoutDeserializer().deserialize( 'Test', { schema: { id: 'S1' }, type: 'table' } );
		expect( layout.getSchema() ).toBe( 'S1' );
		expect( JSON.parse( new LayoutSerializer().serializeLayout( layout ) ).schema ).toEqual( { id: 'S1' } );
	} );
	it( 'does not reinterpret a same-spelled old schema name', () => {
		const layout = new LayoutDeserializer().deserialize( 'Test', { schema: 'S1', type: 'table' } );
		expect( layout.getSchemaReference() ).toBe( 'S1' );
		expect( JSON.parse( new LayoutSerializer().serializeLayout( layout ) ).schema ).toBe( 'S1' );
	} );
} );
