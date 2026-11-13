import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { BasePropertyType } from '@/domain/PropertyType';
import { TextType, type TextProperty } from './Text';
import { newStringValue, ValueType, type StringValue } from '@/domain/Value';
export interface WikitextProperty extends TextProperty { readonly wikitext: string }
export function isSchemaExecution( property: Pick<PropertyDefinition, 'type'> & { readonly execute?: boolean } ): boolean {
	return property.type === 'wikitext';
}
export class WikitextType extends BasePropertyType<WikitextProperty, StringValue> {
	public static readonly typeName = 'wikitext';

	public static readonly valueType = ValueType.String;

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( 'Wikitext' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): WikitextProperty {
		return { ...new TextType().createPropertyDefinitionFromJson( base, json ), wikitext: json.wikitext ?? '' };
	}
}

/** Whitespace is meaningful in wikitext and must survive editing and reopening. */
export function newWikitextValue( ...parts: string[] ): StringValue {
	return { type: ValueType.String, parts: parts.filter( ( part ) => part.trim() !== '' ) };
}
