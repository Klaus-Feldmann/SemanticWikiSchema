<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\PropertyType\Types;

use AEWI\SWS\Domain\PropertyType\PropertyType;
use AEWI\SWS\Domain\Schema\Property\NumberProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\NumberValue;
use AEWI\SWS\Domain\Value\ValueType;

class NumberType implements PropertyType {

	public const NAME = 'number';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Number;
	}

	public function getDisplayAttributeNames(): array {
		return [ 'precision' ];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): NumberProperty {
		return NumberProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof NumberProperty ) {
			return [];
		}

		if ( !$value instanceof NumberValue ) {
			if ( $definition->isRequired() ) {
				return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
			}
			return [];
		}

		$violations = [];

		if ( $definition->hasMinimum() && $value->number < $definition->getMinimum() ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'min-value',
				args: [ $definition->getMinimum() ],
				severity: $definition->severityOf( 'minimum' ),
			);
		}

		if ( $definition->hasMaximum() && $value->number > $definition->getMaximum() ) {
			$violations[] = new Violation(
				propertyName: null,
				code: 'max-value',
				args: [ $definition->getMaximum() ],
				severity: $definition->severityOf( 'maximum' ),
			);
		}

		return $violations;
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof NumberValue ? [ (string)$value->number ] : [];
	}

}
