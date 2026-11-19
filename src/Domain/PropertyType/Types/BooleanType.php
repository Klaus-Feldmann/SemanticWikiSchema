<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\PropertyType\Types;

use AEWI\SWS\Domain\PropertyType\PropertyType;
use AEWI\SWS\Domain\Schema\Property\BooleanProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Domain\Value\BooleanValue;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\ValueType;

class BooleanType implements PropertyType {

	public const NAME = 'boolean';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::Boolean;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): BooleanProperty {
		return BooleanProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$definition instanceof BooleanProperty ) {
			return [];
		}

		if ( !$value instanceof BooleanValue && $definition->isRequired() ) {
			return [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ];
		}

		return [];
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return [];
	}

}
