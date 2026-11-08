import { PropertyName, type PropertyDefinition } from '@/domain/PropertyDefinition';
import { newUrlValue, urlEntries, type Value } from '@/domain/Value';

/** Presentation copies only: never rename a stored field or mutate its Schema. */
export function expandUrlDisplayRows<T extends { propertyDefinition: PropertyDefinition; value?: Value; external?: unknown }>( rows: T[] ): ( T & { sourcePropertyName: string } )[] {
	return rows.flatMap( ( row ) => {
		const sourcePropertyName = row.propertyDefinition.name.toString();
		const entries = row.propertyDefinition.type === 'url' ? urlEntries( row.value ) : [];
		if ( !entries.length ) {
			return [ { ...row, sourcePropertyName } ];
		}
		return entries.map( ( entry, index ) => {
			const caption = entry.caption?.trim();
			return { ...row, sourcePropertyName, external: index === 0 ? row.external : undefined,
				propertyDefinition: { ...row.propertyDefinition, hideUrlCaption: true,
					...( caption ? { name: new PropertyName( caption ), wikiLink: { ...row.propertyDefinition.wikiLink, label: !!entry.captionPage?.trim(), labelTarget: entry.captionPage?.trim() } } : {} ) },
				value: newUrlValue( [ entry ] ) };
		} );
	} );
}
