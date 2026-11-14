import { Neo } from '@/Neo';
import { PropertyTypeRegistry } from '@/domain/PropertyType';
import type { Severity } from '@/domain/Severity';
import { extractSeverities } from '@/domain/SeverityNormalizer';
import { Value, newUrlValue } from '@/domain/Value';
import { ValueDeserializer } from '@/persistence/ValueDeserializer';

export class PropertyName {

	private readonly name: string;

	/**
	 * @param name - The name of the property.
	 * @param placeholder - Whether the name is a placeholder, used when creating a new property.
	 */
	public constructor( name: string, placeholder: boolean = false ) {
		this.name = name.trim();

		if ( !PropertyName.isValid( name ) && !placeholder ) {
			throw new Error( 'Invalid PropertyName' );
		}
	}

	public toString(): string {
		return this.name;
	}

	public static isValid( name: string ): boolean {
		return name.trim() !== '';
	}

}

export interface ExternalProperty { readonly source: 'wikidata' | 'wikibase-local'; readonly id: string }

export interface PropertyDefinition {

	readonly name: PropertyName;
	readonly type: string;
	readonly description: string;
	readonly labels?: Readonly<Record<string, string>>;
	readonly required: boolean;
	readonly default?: Value;
	readonly externalProperty?: ExternalProperty;
    readonly fieldNotes?: { type: 'text' | 'wikitext'; annotate?: boolean };
	readonly wikiLink?: { values?: boolean; namespace?: string; label?: boolean; labelTarget?: string; externalTarget?: 'source' | 'local'; dateBceDe?: string; dateBceEn?: string };

	/**
	 * Severity per Constraint name for Constraints written in the object form
	 * (ADR 26). Absent means every Constraint has the default `warning` severity;
	 * the map is only set when at least one Constraint is annotated, so shorthand
	 * Schemas produce domain objects identical to factory-built ones.
	 */
	readonly constraintSeverities?: Readonly<Record<string, Severity>>;

}

/**
 * The Constraint-severity change to emit from an attributes editor: the given Constraint set to
 * the given severity, the other annotations kept. Warning is the default and is not stored, so
 * setting it removes the annotation, and an emptied map is dropped altogether — a definition
 * with no annotations stays identical to one that never had any.
 */
export function withConstraintSeverity(
	property: PropertyDefinition,
	constraint: string,
	severity: Severity,
): Pick<PropertyDefinition, 'constraintSeverities'> {
	const severities: Record<string, Severity> = { ...property.constraintSeverities };

	if ( severity === 'warning' ) {
		delete severities[ constraint ];
	} else {
		severities[ constraint ] = severity;
	}

	return { constraintSeverities: Object.keys( severities ).length > 0 ? severities : undefined };
}

/**
 * The severity map without the entries of Constraints that the given change deliberately unsets:
 * an unticked boolean (false) or an emptied list ([]). Such a Constraint carries no severity, so
 * the Constraint set again starts at the default.
 *
 * A Constraint whose rule applies while it is false, such as `multiple`, keeps its entry too:
 * unticking it sets the rule rather than clearing it.
 *
 * So does `uniqueLanguages`, which turns false whenever another option for the number of values is
 * picked: like the one for `multiple` beside it, its severity survives the author trying the other
 * options.
 *
 * A bound cleared to undefined keeps its entry: a number input reports interim text such as "1."
 * as empty, so pruning there would lose the choice while the author is still typing. Serialization
 * drops it if the bound is still empty when saving.
 */
export function withoutSeveritiesOfClearedConstraints<T extends PropertyDefinition>(
	property: T,
	changes: Partial<T>,
): Pick<PropertyDefinition, 'constraintSeverities'> {
	const severities: Record<string, Severity> = { ...property.constraintSeverities };

	for ( const [ key, value ] of Object.entries( changes ) ) {
		if ( key !== 'constraintSeverities' && !SEVERITY_SURVIVES_CLEARING.includes( key ) && constraintIsCleared( value ) ) {
			delete severities[ key ];
		}
	}

	return { constraintSeverities: Object.keys( severities ).length > 0 ? severities : undefined };
}

const SEVERITY_SURVIVES_CLEARING = [ 'multiple', 'uniqueLanguages' ];

function constraintIsCleared( value: unknown ): boolean {
	return value === false || ( Array.isArray( value ) && value.length === 0 );
}

export interface MultiStringProperty extends PropertyDefinition {

	readonly multiple: boolean;
	readonly uniqueItems: boolean;

}

/**
 * In production code, prefer using an instance of PropertyDefinitionDeserializer
 */
export function createPropertyDefinitionFromJson( id: string, json: any ): PropertyDefinition {
	return Neo.getInstance().getPropertyDefinitionDeserializer().propertyDefinitionFromJson( id, json );
}

export class PropertyDefinitionDeserializer {

	public constructor(
		private readonly registry: PropertyTypeRegistry,
		private readonly valueDeserializer: ValueDeserializer,
	) {}

	public propertyDefinitionFromJson( name: string | PropertyName, json: any ): PropertyDefinition {
		const [ values, severities ] = extractSeverities( json );

		// Severity is a Constraint concept. Display Attributes are explicitly not
		// Constraints (they are overridable per Layout), so a severity on one is
		// meaningless. Drop it: left in the map it would make serialization re-emit
		// the attribute in object form and break every consumer reading a scalar.
		if ( this.registry.hasType( json.type ) ) {
			for ( const attribute of this.registry.getType( json.type ).getDisplayAttributeNames() ) {
				delete severities[ attribute ];
			}
		}

		const base: PropertyDefinition = {
			name: typeof name === 'string' ? new PropertyName( name ) : name,
			type: json.type as string,
            ...( values.fieldNotes ? { fieldNotes: values.fieldNotes as PropertyDefinition['fieldNotes'] } : {} ),
			description: values.description as string ?? '',
			...( normalizedLabels( values.labels ) === undefined ? {} : { labels: normalizedLabels( values.labels ) } ),
			required: values.required as boolean ?? false,
			...( values.externalProperty !== undefined ? { externalProperty: values.externalProperty as ExternalProperty } : {} ),
			...( values.wikiLink !== undefined ? { wikiLink: values.wikiLink as PropertyDefinition['wikiLink'] } : {} ),
			default: values.default !== undefined && values.default !== null ?
				( json.type === 'url' ? newUrlValue( values.default, true ) : this.valueDeserializer.deserialize( values.default, json.type ) ) :
				undefined,
			...( Object.keys( severities ).length > 0 ? { constraintSeverities: severities } : {} ),
		};

		// A type owned by a disabled or failed extension is not registered. Degrade
		// to the base definition so the rest of the Schema still loads and renders.
		// Retain the original type-specific keys (constraints, display attributes)
		// so they are not silently dropped when the Schema is later re-saved. The
		// normalized values (not the raw JSON) are spread so serialization re-wraps
		// every annotated key through the same applySeverities path.
		if ( !this.registry.hasType( json.type ) ) {
			return { ...values, ...base };
		}

		return this.registry.getType( json.type ).createPropertyDefinitionFromJson( base, values );
	}

}

function normalizedLabels( value: unknown ): Record<string, string> | undefined {
	if ( value === null || typeof value !== 'object' || Array.isArray( value ) ) { return undefined; }
	const entries = Object.entries( value ).filter( ( [ tag, label ] ) => /^[a-z]{1,8}(-[a-z0-9]{1,8})*$/i.test( tag ) && typeof label === 'string' && label.trim() !== '' )
		.map( ( [ tag, label ] ) => [ tag.toLowerCase(), ( label as string ).trim() ] );
	return entries.length === 0 ? undefined : Object.fromEntries( entries );
}
