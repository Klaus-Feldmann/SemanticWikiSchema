import { TextType, type TextProperty } from './Text';
import { BasePropertyType } from '@/domain/PropertyType';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValueType, type StringValue, newStringValue } from '@/domain/Value';
export type WikitextValueProperty = TextProperty;
export class WikitextValueType extends BasePropertyType<TextProperty, StringValue> {
	public static readonly typeName = 'wikitextValue';
	public static readonly valueType = ValueType.String;
	public getDisplayAttributeNames(): string[] { return []; }
	public getExampleValue(): StringValue { return newStringValue( '[[Beispiel]]' ); }
	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): TextProperty {
		return new TextType().createPropertyDefinitionFromJson( base, json );
	}
}
