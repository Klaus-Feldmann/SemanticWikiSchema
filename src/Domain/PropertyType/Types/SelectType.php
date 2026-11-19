<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\PropertyType\Types;

use AEWI\SWS\Domain\PropertyType\PropertyType;
use AEWI\SWS\Domain\Schema\Property\SelectProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Domain\Value\ValueType;

class SelectType implements PropertyType {

	public const NAME = 'select';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): SelectProperty {
		return SelectProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof SelectProperty ) {
			return [];
		}

		$parts = $value instanceof StringValue ? $value->strings : [];

		if ( $definition->isRequired() && $parts === [] ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		$validIds = array_flip( $definition->getOptionIds() );

		$violations = [];

		foreach ( $parts as $index => $part ) {
			if ( !isset( $validIds[ $part ] ) ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'invalid-option',
					args: [ $part ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'options' ),
				);
			}
		}

		if ( !$definition->allowsMultipleValues() && count( $parts ) > 1 ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'single-value-only',
				severity: $definition->severityOf( 'multiple' ),
			);
		}

		return $violations;
	}

	/**
	 * The stored option ids are read as their labels from the definition.
	 */
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue || !$definition instanceof SelectProperty ) {
			return [];
		}

		$labels = [];

		foreach ( $value->strings as $id ) {
			$option = $definition->getOption( $id );

			if ( $option !== null ) {
				$labels[] = $option->getLabel();
			}
		}

		return $labels;
	}

}
