import { MultiStringProperty, PropertyDefinition } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType } from '@/domain/PropertyType';

export type ImageSource = 'wikidata' | 'wikibase-local' | 'local' | 'commons';
export interface ImageProperty extends MultiStringProperty { imageSource?: ImageSource }

export class ImageType extends BasePropertyType<ImageProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'image';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( 'commons:Example.jpg' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): ImageProperty {
		return {
			...base,
			imageSource: json.imageSource ?? base.externalProperty?.source ?? 'local',
			multiple: json.multiple ?? false,
			uniqueItems: json.uniqueItems ?? false,
		} as ImageProperty;
	}

}
