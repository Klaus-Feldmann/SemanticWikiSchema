<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Domain\ImageReference;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyType;
use ProfessionalWiki\SWS\Domain\Schema\Property\ImageProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\ValueType;

final class ImageType implements PropertyType {
	public const NAME = 'image';
	public const REFERENCE_PATTERN = ImageReference::PATTERN;
	public function getTypeName(): string { return self::NAME; }
	public function getValueType(): ValueType { return ValueType::String; }
	public function getDisplayAttributeNames(): array { return []; }
	public function buildPropertyDefinitionFromJson( PropertyCore $core, array $property ): ImageProperty {
		return ImageProperty::fromPartialJson( $core, $property );
	}
	public function validate( NeoValue $value, PropertyDefinition $definition ): array {
		if ( !$value instanceof StringValue || !$definition instanceof ImageProperty ) {
			return [ new Violation( propertyName: null, code: 'invalid-image', severity: Severity::Error ) ];
		}
		$errors = [];
		if ( $value->isEmpty() && $definition->isRequired() ) {
			$errors[] = new Violation( propertyName: null, code: 'required', severity: $definition->severityOf( 'required' ) );
		}
		if ( !$definition->allowsMultipleValues() && count( $value->strings ) > 1 ) {
			$errors[] = new Violation( propertyName: null, code: 'image-single', severity: Severity::Error );
		}
		foreach ( $value->strings as $index => $reference ) {
			if ( ImageReference::parse( $reference ) === null ) {
				$errors[] = new Violation( propertyName: null, code: 'invalid-image', valuePartIndex: $index, severity: Severity::Error );
			}
		}
		if ( $definition->enforcesUniqueValues() && count( array_unique( $value->strings ) ) !== count( $value->strings ) ) {
			$errors[] = new Violation( propertyName: null, code: 'unique', severity: $definition->severityOf( 'uniqueItems' ) );
		}
		return $errors;
	}
	public function searchText( NeoValue $value, ?PropertyDefinition $definition ): array {
		return $value instanceof StringValue ? $value->strings : [];
	}
}
