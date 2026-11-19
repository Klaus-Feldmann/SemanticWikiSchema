<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Domain\Schema\Property;

use InvalidArgumentException;
use AEWI\SWS\Domain\PropertyType\Types\BooleanType;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Schema\PropertyDefinition;

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
