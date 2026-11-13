import { type LocalizedIdentity, identityLabel, identityDescription } from './LocalizedIdentity';
import type { SchemaReference } from './SchemaReference';
import { cloneInitialValue } from './Value';
import { isSchemaExecution } from './propertyTypes/Wikitext';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';

export type SchemaName = string;

export class Schema {

	public constructor(
		private readonly name: SchemaName,
		private readonly description: string,
		private readonly properties: PropertyDefinitionList,
		private readonly subjectsDependent: boolean,
		private readonly identity: LocalizedIdentity | null = null,
		private readonly id: string | null = null,
		private readonly importOrigin: Record<string, unknown> | null = null,
	) {
		if ( id !== null && ( !/^S[1-9][0-9]{0,17}$/.test( id ) || id !== name || identity === null ) ) {
			throw new Error( 'Schema ID, page key and identity metadata must agree' );
		}
	}

	public getImportOrigin(): Record<string, unknown> | null {
		return this.importOrigin;
	}

	public getId(): string | null {
		return this.id;
	}

	public getIdentity(): LocalizedIdentity | null {
		return this.identity;
	}

	public getLabel(): string {
		return identityLabel( this.identity, this.name );
	}

	public getReference(): SchemaReference {
		return this.id === null ? this.name : { id: this.id };
	}

	public withIdentity( identity: LocalizedIdentity | null ): Schema {
		return new Schema( this.name, this.description, this.properties, this.subjectsDependent, identity, this.id, this.importOrigin );
	}

	public withPropertyDefinitions( properties: PropertyDefinitionList ): Schema {
		return new Schema( this.name, this.description, properties, this.subjectsDependent, this.identity, this.id, this.importOrigin );
	}

	public asNew( label: string ): Schema {
		const language = typeof mw === 'undefined' ? 'en' : mw.config?.get( 'wgContentLanguage' ) || 'en';
		return new Schema( label, this.getDescription(), this.properties, this.subjectsDependent,
			{ identityLanguage: language, labels: { [ language ]: label }, descriptions: { [ language ]: this.getDescription() } } );
	}

	public getName(): SchemaName {
		return this.name;
	}

	public getDescription(): string {
		return identityDescription( this.identity, this.description );
	}

	public getPropertyDefinitions(): PropertyDefinitionList {
		return this.properties;
	}

	public subjectsAreDependent(): boolean {
		return this.subjectsDependent;
	}

	public getPropertyDefinition( propertyName: string|PropertyName ): PropertyDefinition {
		return this.properties.get(
			propertyName instanceof PropertyName ? propertyName : new PropertyName( propertyName ),
		);
	}

	public withName( name: SchemaName ): Schema {
		return new Schema( name, this.description, this.properties, this.subjectsDependent, this.identity, this.id, this.importOrigin );
	}

	public withDescription( description: string ): Schema {
		return new Schema( this.name, description, this.properties, this.subjectsDependent, this.identity === null ? null : { ...this.identity, descriptions: { ...this.identity.descriptions, [ this.identity.identityLanguage ]: description } }, this.id, this.importOrigin );
	}

	public withSubjectsAreDependent( subjectsAreDependent: boolean ): Schema {
		return new Schema( this.name, this.description, this.properties, subjectsAreDependent, this.identity, this.id, this.importOrigin );
	}

	public withAddedPropertyDefinition( property: PropertyDefinition ): Schema {
		return new Schema(
			this.name,
			this.description,
			new PropertyDefinitionList( [ ...this.properties, property ] ),
			this.subjectsDependent, this.identity, this.id, this.importOrigin,
		);
	}

	public withReorderedPropertyDefinitions( names: PropertyName[] ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.reordered( names ),
			this.subjectsDependent, this.identity, this.id, this.importOrigin,
		);
	}

	public withRemovedPropertyDefinition( propertyName: PropertyName ): Schema {
		return new Schema(
			this.name,
			this.description,
			this.properties.withoutNames( [ propertyName ] ),
			this.subjectsDependent, this.identity, this.id, this.importOrigin,
		);
	}

	public statementsFrom( existing: StatementList ): StatementList {
		return new StatementList(
			[ ...this.properties ].filter( ( definition ) => !isSchemaExecution( definition ) ).map( ( definition ) =>
				existing.has( definition.name ) ?
					existing.get( definition.name ) :
					new Statement( definition.name, definition.type, undefined ),
			), existing.fieldNotes, existing.notesEdited,
		);
	}

	/** Only for creation: editing existing subjects continues to use statementsFrom(). */
	public initialStatements(): StatementList {
		return new StatementList( [ ...this.properties ].filter( ( definition ) => !isSchemaExecution( definition ) ).map(
			( definition ) => new Statement( definition.name, definition.type,
				definition.default === undefined ? undefined : cloneInitialValue( definition.default ) ),
		) );
	}

	public blankStatements(): StatementList {
		return this.statementsFrom( new StatementList( [] ) );
	}

	/**
	 * The edited statements, then those of `existing` whose property the Schema does not declare, which a form
	 * built from statementsFrom() leaves out.
	 */
	public statementsAfterEdit( existing: StatementList, edited: StatementList ): StatementList {
		return new StatementList( [
			...edited,
			...[ ...existing ].filter( ( statement ) => !this.properties.has( statement.propertyName ) || isSchemaExecution( this.properties.get( statement.propertyName ) ) ),
		], edited.fieldNotes, edited.notesEdited );
	}

}
