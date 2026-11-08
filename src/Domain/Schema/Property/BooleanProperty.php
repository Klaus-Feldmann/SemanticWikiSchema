<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;

class BooleanProperty extends PropertyDefinition {

	public function __construct( PropertyCore $core ) {
		if ( $core->default !== null && !is_bool( $core->default ) ) {
			throw new InvalidArgumentException( 'Boolean property default must be a boolean' );
		}

		parent::__construct( $core );
	}

	public function getPropertyType(): string {
		return BooleanType::NAME;
	}

	public static function fromPartialJson( PropertyCore $core, array $property ): self {
		return new self( core: $core );
	}

	public function nonCoreToJson(): array {
		return [];
	}

	public function toJsonSchema(): array {
		return [ 'type' => 'boolean' ];
	}

}
