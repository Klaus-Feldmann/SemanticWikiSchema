<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Domain\PropertyType\PropertyType;
use ProfessionalWiki\SWS\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\ValueType;

class RelationType implements PropertyType {

	public const NAME = 'relation';

	public function __construct(
		private readonly SchemaReferenceParser $schemaReferenceParser
	) {
	}

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Relation;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): RelationProperty {
		return RelationProperty::fromPartialJson( $core, $property, $this->schemaReferenceParser );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof RelationProperty ) {
			return [];
		}

		$relations = $value instanceof RelationValue ? $value->relations : [];

		if ( $definition->isRequired() && $relations === [] ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$violations = [];

		if ( !$definition->allowsMultipleValues() && count( $relations ) > 1 ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'single-value-only',
				severity: $definition->severityOf( 'multiple' ),
			);
		}

		return $violations;
	}

	/**
	 * Readers find the target by its own label.
	 */
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return [];
	}

}
