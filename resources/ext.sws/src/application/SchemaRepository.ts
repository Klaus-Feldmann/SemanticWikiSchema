import { InMemorySchemaLookup, SchemaLookup } from '@/application/SchemaLookup';
import { Schema } from '@/domain/Schema.ts';

export interface SchemaRepository extends SchemaLookup {

	importSchemaDocument?( document: string, selected: string[] ): Promise<{ created: string[] }>;
	saveSchema( schema: Schema, comment?: string ): Promise<void>;
	createSchema( schema: Schema, comment?: string, requestKey?: string ): Promise<Schema>;

}

export class InMemorySchemaRepository extends InMemorySchemaLookup implements SchemaRepository {

	public async createSchema( schema: Schema ): Promise<Schema> {
		const id = `S${ this.schemas.size + 1 }`;
		const created = new Schema( id, schema.getDescription(), schema.getPropertyDefinitions(), schema.subjectsAreDependent(), schema.asNew( schema.getName() ).getIdentity(), id );
		this.schemas.set( id, created );
		return created;
	}

	public async saveSchema( schema: Schema ): Promise<void> {
		this.schemas.set( schema.getName(), schema );
	}

}
