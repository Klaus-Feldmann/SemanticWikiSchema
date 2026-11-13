import { schemaReferenceName, type SchemaReference } from '@/domain/SchemaReference';
import type { SchemaName } from '@/domain/Schema';
import type { PropertyName } from '@/domain/PropertyDefinition';

export type LayoutName = string;

export interface DisplayRule {
	readonly property: PropertyName;
	readonly displayAttributes?: Record<string, unknown>;
}

export class Layout {

	public constructor(
		private readonly name: LayoutName,
		private readonly schema: SchemaReference,
		private readonly type: string,
		private readonly description: string,
		private readonly displayRules: DisplayRule[],
		private readonly settings: Record<string, unknown>,
	) {
	}

	public getName(): LayoutName {
		return this.name;
	}

	public getSchema(): SchemaName {
		return schemaReferenceName( this.schema );
	}

	public getSchemaReference(): SchemaReference {
		return this.schema;
	}

	public getType(): string {
		return this.type;
	}

	public getDescription(): string {
		return this.description;
	}

	public getDisplayRules(): DisplayRule[] {
		return this.displayRules;
	}

	public getSettings(): Record<string, unknown> {
		return this.settings;
	}

}
