import { importSchemaDocument } from '@/components/SchemasPage/schemaIdImport';
import { Schema, type SchemaName } from '@/domain/Schema';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';
import type { SchemaRepository } from '@/application/SchemaRepository';
import type { SchemaSummaryPage } from '@/application/SchemaLookup';
import { SchemaSerializer } from '@/persistence/SchemaSerializer.ts';
import { SchemaDeserializer } from '@/persistence/SchemaDeserializer.ts';
import { PageSaver } from '@/persistence/PageSaver.ts';

export class RestSchemaRepository implements SchemaRepository {

	public constructor(
		private readonly mediaWikiRestApiUrl: string,
		private readonly httpClient: HttpClient,
		private readonly serializer: SchemaSerializer,
		private readonly deserializer: SchemaDeserializer,
		private readonly pageSaver: PageSaver,
	) {
	}

	public async getSchema( schemaName: SchemaName ): Promise<Schema> {
		// MediaWiki sends a page with a Last-Modified date but no max-age, so browsers may answer
		// a read from their cache with a version older than the latest edit.
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/v1/page/Schema:${ encodeURIComponent( schemaName ) }`,
			{ headers: { 'Cache-Control': 'no-cache' } },
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching schema' );
		}

		const data = await response.json();
		const schema = JSON.parse( data.source );

		if ( schema.propertyDefinitions === undefined ) {
			throw new Error( 'Schema propertyDefinitions is undefined' );
		}

		return this.deserializer.deserialize( schemaName, schema );
	}

	public async getSchemaNames( search: string ): Promise<string[]> {
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/sws/v0/schema-names/${ encodeURIComponent( search ) }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching schemas' );
		}

		return await response.json();
	}

	public async getSchemaSummaries( cursor: string | null, limit: number ): Promise<SchemaSummaryPage> {
		const cursorParam = cursor === null ? '' : `&cursor=${ encodeURIComponent( cursor ) }`;
		const response = await this.httpClient.get(
			`${ this.mediaWikiRestApiUrl }/sws/v0/schemas?limit=${ limit }${ cursorParam }`,
		);

		if ( !response.ok ) {
			throw new Error( 'Error fetching schema summaries' );
		}

		return await response.json();
	}

	public async importSchemaDocument( document: string, selected: string[] ): Promise<{ created: string[] }> {
		return importSchemaDocument( document, selected, this.mediaWikiRestApiUrl, this.httpClient,
			mw.config.get( 'wgContentLanguage' ) || 'en' );
	}

	public async createSchema( schema: Schema, comment?: string, requestKey?: string ): Promise<Schema> {
		const response = await this.httpClient.post( `${ this.mediaWikiRestApiUrl }/sws/v0/schema`, {
			schema: this.serializer.schemaToJson( schema ), comment, requestKey,
		} );
		if ( !response.ok ) {
			throw new Error( 'Error creating schema' );
		}
		const data = await response.json();
		if ( typeof data.schema?.id !== 'string' ) {
			throw new Error( 'Created schema has no stable ID' );
		}
		return this.deserializer.deserialize( data.schema.id, data.schema );
	}

	public async saveSchema( schema: Schema, comment?: string ): Promise<void> {
		const status = await this.pageSaver.savePage(
			`Schema:${ encodeURIComponent( schema.getName() ) }`,
			this.serializeSchema( schema ),
			comment || 'Update schema via SWS REST API',
			'SWSSchema',
		);

		if ( !status.success ) {
			throw new Error( status.message );
		}
	}

	private serializeSchema( schema: Schema ): string {
		return this.serializer.serializeSchema( schema );
	}

}
