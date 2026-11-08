<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\SWS\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\SeverityNormalizer;
use Throwable;

abstract class PropertyDefinition {

	public function __construct(
		private readonly PropertyCore $core,
	) {
	}

	abstract public function getPropertyType(): string;

	public function isSchemaExecution(): bool { return false; }

	public function getDescription(): string {
		return $this->core->description;
	}

	public function isRequired(): bool {
		return $this->core->required;
	}

	public function severityOf( string $constraint ): Severity {
		return $this->core->constraintSeverities[$constraint] ?? Severity::Warning;
	}

	public function getWikiLink(): ?array {
		return $this->core->wikiLink;
	}

	public function getExternalProperty(): ?ExternalProperty {
		return $this->core->externalProperty;
	}

	public function getDefault(): mixed {
		return $this->core->default;
	}

	public function hasDefault(): bool {
		return $this->core->default !== null;
	}

	public function allowsMultipleValues(): bool {
		return false;
	}

	public function toJson(): array {
		return SeverityNormalizer::apply(
			array_merge( $this->coreToJson(), $this->nonCoreToJson() ),
			$this->core->constraintSeverities
		);
	}

	/**
	 * The fields common to every Property Type.
	 */
	protected function coreToJson(): array {
		return [
			'type' => $this->getPropertyType(),
			'description' => $this->getDescription(),
			'required' => $this->isRequired(),
			'default' => $this->getDefault(),
			...( $this->getWikiLink() === null ? [] : [ 'wikiLink' => $this->getWikiLink() ] ),
			...( $this->getExternalProperty() === null ? [] : [ 'externalProperty' => $this->getExternalProperty()->toJson() ] ),
		];
	}

	/**
	 * Type-specific fields beyond the common core (type/description/required/default).
	 *
	 * @internal Public only so that PHP-side serializers (REST `toJson`, the Lua
	 * `SchemaLuaSerializer`, and persistence) can share one extension point per type.
	 * Not intended as a general UI-layer API.
	 */
	abstract public function nonCoreToJson(): array;

	/**
	 * JSON Schema (draft 2020-12) for the value of a Statement of this property: what the Property
	 * Type's validate() checks about the value itself, in a form standard validators apply. `true`
	 * when nothing is known about the value.
	 *
	 * @return array<string, mixed>|true
	 */
	abstract public function toJsonSchema(): array|true;

	/**
	 * JSON Schema for a value held as a list of parts: the schema of one part, plus how many parts
	 * this definition admits.
	 *
	 * @param array<string, mixed>|bool $partSchema
	 *
	 * @return array<string, mixed>
	 */
	protected function listValueSchema( array|bool $partSchema ): array {
		return [ 'type' => 'array', 'items' => $partSchema ]
			+ ( $this->allowsMultipleValues() ? [] : [ 'maxItems' => 1 ] )
			+ ( $this->isRequired() ? [ 'minItems' => 1 ] : [] );
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public static function fromJson( array $json, PropertyTypeLookup $propertyTypeLookup ): self {
		// A definition without a usable type name is structurally invalid, as opposed to
		// merely referencing a type no extension registered. Callers skip it; without
		// this guard the lookup below fails with a TypeError they do not catch.
		if ( !array_key_exists( 'type', $json ) || !is_string( $json['type'] ) || $json['type'] === '' ) {
			throw new InvalidArgumentException( 'Property definition needs a non-empty type: ' . json_encode( $json ) );
		}

		[ $values, $severities ] = SeverityNormalizer::extract( $json );

		/** @var string $description */
		$description = $values['description'] ?? '';
		/** @var bool $required */
		$required = $values['required'] ?? false;

		$propertyType = $propertyTypeLookup->getType( $json['type'] );

		// Severity is a Constraint concept. Display Attributes are explicitly not Constraints
		// (they are overridable per Layout), so a severity on one is meaningless. Drop it:
		// left in the map it would make toJson re-emit the attribute as an object and break
		// every consumer that reads it as a scalar.
		if ( $propertyType !== null ) {
			foreach ( $propertyType->getDisplayAttributeNames() as $displayAttribute ) {
				unset( $severities[$displayAttribute] );
			}
		}

		$wikiLink = $values['wikiLink'] ?? null;
		if ( $wikiLink !== null ) {
			if ( !is_array( $wikiLink ) ) {
				throw new InvalidArgumentException( 'wikiLink must be an object' );
			}
			foreach ( $wikiLink as $key => $value ) {
				$expected = match ( $key ) {
					'values', 'label' => is_bool( $value ),
					'namespace', 'labelTarget' => is_string( $value ),
					'dateBceDe', 'dateBceEn' => is_string( $value ) && strlen( $value ) <= 64 && trim( $value ) !== '',
					'externalTarget' => in_array( $value, [ 'source', 'local' ], true ),
					default => false,
				};
				if ( !$expected ) {
					throw new InvalidArgumentException( 'Invalid wikiLink option: ' . $key );
				}
			}
		}

		$propertyCore = new PropertyCore(
			description: $description,
			required: $required,
			default: $values['default'] ?? null,
			constraintSeverities: $severities,
			wikiLink: $wikiLink,
			externalProperty: isset( $values['externalProperty'] ) ? ExternalProperty::fromJson( $values['externalProperty'] ) : null,
		);

		// The extension owning the type is disabled or failed to load. Preserve the property
		// (incl. its object-form constraint severities) so the Schema still serves it and a
		// re-save does not drop it; it never validates, so the severity map is inert here.
		// It is built from the normalized $values (not raw $json) so toJson re-wraps every
		// annotated key through the same apply() path as registered types.
		if ( $propertyType === null ) {
			return UnregisteredTypeProperty::fromPartialJson( $propertyCore, $values );
		}

		try {
			return $propertyType->buildPropertyDefinitionFromJson( $propertyCore, $values );
		} catch ( Throwable $e ) {
			throw new InvalidArgumentException( 'Invalid property definition: ' . json_encode( $json ), 0, $e );
		}
	}

}
