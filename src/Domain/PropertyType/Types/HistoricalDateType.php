<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Domain\HistoricalDate;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyType;
use ProfessionalWiki\SWS\Domain\Schema\Property\HistoricalDateProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\ValueType;

class HistoricalDateType implements PropertyType {
	public const NAME = 'historicalDate';
	public function getTypeName(): string { return self::NAME; }
	public function getValueType(): ValueType { return ValueType::String; }
	public function getDisplayAttributeNames(): array { return []; }
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): HistoricalDateProperty {
		return new HistoricalDateProperty( $core );
	}
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue || count( $value->strings ) > 1 ) {
			return [ new Violation( propertyName: null, code: 'invalid-date', severity: Severity::Error ) ];
		}
		if ( $value->isEmpty() ) {
			return $definition->isRequired() ? [ new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) ) ] : [];
		}
		return HistoricalDate::parse( $value->strings[0] ) === null ?
			[ new Violation( propertyName: null, code: 'invalid-date', severity: Severity::Error ) ] : [];
	}
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}
}
