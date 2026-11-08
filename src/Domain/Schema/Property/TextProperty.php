<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;

class TextProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly bool $multiple,
		private readonly bool $uniqueItems,
		private readonly ?int $minLength,
		private readonly ?int $maxLength,
	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return TextType::NAME;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public function enforcesUniqueValues(): bool {
		return $this->uniqueItems;
	}

	public function getMinLength(): ?int {
		return $this->minLength;
	}

	public function hasMinLength(): bool {
		return $this->minLength !== null;
	}

	public function getMaxLength(): ?int {
		return $this->maxLength;
	}

	public function hasMaxLength(): bool {
		return $this->maxLength !== null;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			multiple: $property['multiple'] ?? false,
			uniqueItems: $property['uniqueItems'] ?? false,
			minLength: $property['minLength'] ?? null,
			maxLength: $property['maxLength'] ?? null,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'multiple' => $this->allowsMultipleValues(),
			'uniqueItems' => $this->enforcesUniqueValues(),
			'minLength' => $this->getMinLength(),
			'maxLength' => $this->getMaxLength(),
		];
	}

	public function toJsonSchema(): array {
		$part = [ 'type' => 'string' ];

		if ( $this->hasMinLength() ) {
			$part['minLength'] = $this->getMinLength();
		}

		if ( $this->hasMaxLength() ) {
			$part['maxLength'] = $this->getMaxLength();
		}

		return $this->listValueSchema( $part )
			+ ( $this->enforcesUniqueValues() ? [ 'uniqueItems' => true ] : [] );
	}

}
