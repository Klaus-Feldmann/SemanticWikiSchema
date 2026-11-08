import type { MultiStringProperty, PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export interface PageProperty extends MultiStringProperty {

	readonly pageNamespace?: number;
	readonly maxLength?: number;
	readonly minLength?: number;

}

export class PageType extends BasePropertyType<PageProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'page';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( 'Main Page' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): PageProperty {
		return {
			...base,
			pageNamespace: json.pageNamespace ?? undefined,
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? false,
			minLength: json.minLength ?? undefined,
			maxLength: json.maxLength ?? undefined,
		} as PageProperty;
	}

}

type PagePropertyAttributes = Omit<Partial<PageProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newPageProperty( attributes: PagePropertyAttributes = {} ): PageProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Page' ),
		type: PageType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		multiple: attributes.multiple ?? false,
		uniqueItems: attributes.uniqueItems ?? true,
		pageNamespace: attributes.pageNamespace,
		maxLength: attributes.maxLength,
		minLength: attributes.minLength,
	};
}
