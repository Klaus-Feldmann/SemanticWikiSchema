<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain;

use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;

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
