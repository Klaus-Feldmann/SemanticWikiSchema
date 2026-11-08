<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Data;

use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\RelationType;

class TestStatement {

	public static function build(
		string|PropertyName $property = 'TestProperty',
		string|NeoValue $value = 'TestValue',
		string $propertyType = 'text',
	): Statement {
		return new Statement(
			property: new PropertyName( (string)$property ),
			propertyType: $propertyType,
			value: is_string( $value ) ? new StringValue( $value ) : $value
		);
	}

	/**
	 * @param Relation[] $relations
	 */
	public static function buildRelation( string|PropertyName $property = 'TestProperty', array $relations = [] ): Statement {
		return self::build(
			property: $property,
			value: new RelationValue( ...$relations ),
			propertyType: RelationType::NAME
		);
	}

}
