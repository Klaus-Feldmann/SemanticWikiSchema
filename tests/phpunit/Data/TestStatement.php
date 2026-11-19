<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Domain\Relation\Relation;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\RelationValue;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Domain\PropertyType\Types\RelationType;

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
