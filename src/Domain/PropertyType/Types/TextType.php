<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\PropertyType\Types;

use AEWI\SWS\Domain\PropertyType\PropertyType;
use AEWI\SWS\Domain\Schema\Property\TextProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Domain\Value\ValueType;

class TextType implements PropertyType {

	public const NAME = 'text';

	public function getTypeName(): string {
		return self::NAME;
	}

	public function getValueType(): ValueType {
		return ValueType::String;
	}

	public function getDisplayAttributeNames(): array {
		return [];
	}

	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): TextProperty {
		return TextProperty::fromPartialJson( $core, $property );
	}

	/**
	 * @return Violation[]
	 */
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue ) {
			return [];
		}

		if ( !$definition instanceof TextProperty ) {
			return [];
		}

		$violations = [];

		$hasContent = false;
		foreach ( $value->strings as $part ) {
			if ( trim( $part ) !== '' ) {
				$hasContent = true;
				break;
			}
		}

		if ( $definition->isRequired() && !$hasContent ) {
			$violations[] = new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) );
		}

		$violations = array_merge( $violations, $this->validateLengths( $value, $definition ) );

		if ( $definition->enforcesUniqueValues()
			&& count( array_unique( $value->strings ) ) !== count( $value->strings )
		) {
			$violations[] = new Violation( propertyName: null, code: 'unique', severity: $definition->severityOf( 'uniqueItems' ) );
		}

		return $violations;
	}

	/**
	 * @return Violation[]
	 */
	private function validateLengths( StringValue $value, TextProperty $definition ): array {
		if ( !$definition->hasMinLength() && !$definition->hasMaxLength() ) {
			return [];
		}

		$violations = [];

		foreach ( $value->strings as $index => $part ) {
			$length = mb_strlen( trim( $part ) );

			if ( $length === 0 ) {
				continue;
			}

			if ( $definition->hasMinLength() && $length < $definition->getMinLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'min-length',
					args: [ $definition->getMinLength() ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'minLength' ),
				);
			}

			if ( $definition->hasMaxLength() && $length > $definition->getMaxLength() ) {
				$violations[] = new Violation(
					propertyName: null,
					code: 'max-length',
					args: [ $definition->getMaxLength() ],
					valuePartIndex: $index,
					severity: $definition->severityOf( 'maxLength' ),
				);
			}
		}

		return $violations;
	}

	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}

}
