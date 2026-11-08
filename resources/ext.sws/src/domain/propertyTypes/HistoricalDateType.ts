import { BasePropertyType } from '@/domain/PropertyType';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
export class HistoricalDateType extends BasePropertyType<PropertyDefinition, StringValue> {
	public static readonly typeName = 'historicalDate';

	public static readonly valueType = ValueType.String;

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition ): PropertyDefinition {
		return { ...base };
	}

	public getExampleValue(): StringValue {
		return newStringValue( '-0044' );
	}
}
