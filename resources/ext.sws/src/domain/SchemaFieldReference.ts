import type { SchemaReference } from './SchemaReference';

/** Schema and technical field key together identify the field. This is not a global property ID. */
export interface SchemaFieldReference {
	readonly schema: SchemaReference;
	readonly field: string;
}
