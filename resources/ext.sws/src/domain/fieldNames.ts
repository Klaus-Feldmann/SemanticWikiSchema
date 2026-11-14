/** Resolve a technical key in a single schema/snapshot; never infer an identity from a label. */
export function resolveFieldName( reference: string | { toString(): string }, fields: ReadonlyMap<string, unknown> ): string | undefined {
	const key = reference.toString();
	return fields.has( key ) ? key : undefined;
}
