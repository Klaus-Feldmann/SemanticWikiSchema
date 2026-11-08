<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;

class HistoricalDateProperty extends PropertyDefinition {
	public function __construct( PropertyCore $core ) {
		if ( $core->default !== null ) {
			// String values serialize as a parts array in the schema editor; legacy scalar defaults also work.
			$parts = is_array( $core->default ) ? $core->default : [ $core->default ];
			if ( count( $parts ) > 1 ) {
				throw new InvalidArgumentException( 'Historical date defaults must contain at most one value' );
			}
			foreach ( $parts as $part ) {
				if ( !is_string( $part ) || HistoricalDate::parse( $part ) === null ) {
					throw new InvalidArgumentException( 'Invalid historical date default' );
				}
			}
		}
		parent::__construct( $core );
	}

	public function getPropertyType(): string { return 'historicalDate'; }
	public function nonCoreToJson(): array { return []; }
	public function toJsonSchema(): array {
		// Pattern covers the wire shape; calendar validity and year zero are checked by the type.
		return $this->listValueSchema( [ 'type' => 'string', 'pattern' => HistoricalDate::PATTERN ] );
	}
}
