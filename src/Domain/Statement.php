<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain;

use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Value\NeoValue;

readonly class Statement {

	public function __construct(
		private PropertyName $property,
		private string $propertyType,
		private NeoValue $value
	) {
	}

	public function getPropertyName(): PropertyName {
		return $this->property;
	}

	public function getPropertyType(): string {
		return $this->propertyType;
	}

	public function getValue(): NeoValue {
		return $this->value;
	}

}
