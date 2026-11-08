import { Layout, type LayoutName, type DisplayRule } from '@/domain/Layout';
import { PropertyName } from '@/domain/PropertyDefinition';

export class LayoutDeserializer {

	public deserialize( layoutName: LayoutName, data: Record<string, unknown> ): Layout {
		return new Layout(
			layoutName,
			data.schema as string,
			data.type as string,
			( data.description ?? '' ) as string,
			this.deserializeDisplayRules( ( data.displayRules ?? [] ) as Record<string, unknown>[] ),
			( data.settings ?? {} ) as Record<string, unknown>,
		);
	}

	private deserializeDisplayRules( rules: Record<string, unknown>[] ): DisplayRule[] {
		return rules.map( ( rule ) => ( {
			property: new PropertyName( rule.property as string ),
			displayAttributes: rule.displayAttributes as Record<string, unknown> | undefined,
		} ) );
	}

}
