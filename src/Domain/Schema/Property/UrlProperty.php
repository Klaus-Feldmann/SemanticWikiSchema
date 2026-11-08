<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\UrlType;

class UrlProperty extends PropertyDefinition {

	public function __construct(
		PropertyCore $core,
		private readonly bool $multiple,
		private readonly bool $uniqueItems,
	) {
		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return UrlType::NAME;
	}

	public function allowsMultipleValues(): bool {
		return $this->multiple;
	}

	public function enforcesUniqueValues(): bool {
		return $this->uniqueItems;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self(
			core: $core,
			multiple: $property['multiple'] ?? false,
			uniqueItems: $property['uniqueItems'] ?? false,
		);
	}

	public function nonCoreToJson(): array {
		return [
			'multiple' => $this->allowsMultipleValues(),
			'uniqueItems' => $this->enforcesUniqueValues(),
		];
	}

	public function toJsonSchema(): array {
		return $this->listValueSchema( [ 'oneOf' => [
			[ 'type' => 'string', 'pattern' => UrlType::URL_PATTERN ],
			[ 'type' => 'object', 'required' => [ 'url' ], 'additionalProperties' => false,
				'properties' => [
					'url' => [ 'type' => 'string', 'pattern' => UrlType::URL_PATTERN ],
					'label' => [ 'type' => 'string' ], 'caption' => [ 'type' => 'string' ],
					'captionPage' => [ 'type' => 'string' ],
				],
			],
		] ] )
			+ ( $this->enforcesUniqueValues() ? [ 'uniqueItems' => true ] : [] );
	}

}
