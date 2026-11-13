/** A bare string always remains a legacy name; an ID is explicit. */
export type SchemaReference = string | { readonly source?: string; readonly name: string } | { readonly source?: string; readonly id: string };
export function schemaReferenceName( reference: SchemaReference ): string {
	return typeof reference === 'string' ? reference : 'id' in reference ? reference.id : reference.name;
}
export function schemaReferenceText( reference: SchemaReference ): string {
	const name = schemaReferenceName( reference );
	return typeof reference === 'string' || !reference.source ? name : `${ reference.source }:${ name }`;
}
export function isLocalSchemaReference( reference: SchemaReference ): boolean {
	return typeof reference === 'string' || !reference.source;
}
