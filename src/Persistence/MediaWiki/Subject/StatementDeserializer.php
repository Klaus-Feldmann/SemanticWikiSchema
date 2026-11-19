<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Persistence\MediaWiki\Subject;

use AEWI\SWS\Domain\Relation\Relation;
use AEWI\SWS\Domain\Relation\RelationId;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\SubjectIdParser;
use AEWI\SWS\Domain\Value\BooleanValue;
use AEWI\SWS\Domain\Value\MonolingualTextValue;
use AEWI\SWS\Domain\Value\NeoValue;
use AEWI\SWS\Domain\Value\NumberValue;
use AEWI\SWS\Domain\Value\RelationValue;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Domain\Value\UrlValue;
use AEWI\SWS\Domain\Value\UnregisteredTypeValue;
use AEWI\SWS\Domain\Value\ValueType;
use AEWI\SWS\Domain\PropertyType\PropertyTypeLookup;

/**
 * Deserializes statements from persistence-type JSON.
 *
 * Note that the logic is similar, but not identical to, deserializing statements from the API.
 * For example, we need less validation and error handling, and don't need to generate IDs for new relations.
 */
class StatementDeserializer {

	public function __construct(
		private readonly PropertyTypeLookup $propertyTypeLookup,
		private readonly SubjectIdParser $subjectIdParser,
	) {
	}

	public function deserialize( string $propertyName, array $json ): Statement {
		// Revisions written before the key was renamed hold "type", and revision content cannot be migrated.
		$propertyType = $json['propertyType'] ?? $json['type'];

		return new Statement(
			property: new PropertyName( $propertyName ),
			propertyType: $propertyType,
			value: $this->deserializeValue( $propertyType, $json['value'] ),
		);
	}

	private function deserializeValue( string $propertyType, mixed $value ): NeoValue {
		$valueType = $this->propertyTypeLookup->getType( $propertyType )?->getValueType()
			?? ValueType::UnregisteredType;

		return match ( $valueType ) {
			ValueType::Url => new UrlValue( (array)$value ),
			ValueType::String => new StringValue( ...array_values( (array)$value ) ),
			ValueType::Number => new NumberValue( $value ),
			ValueType::Relation => $this->deserializeRelationValue( $value ),
			ValueType::MonolingualText => MonolingualTextValue::fromScalars( $value ),
			ValueType::Boolean => new BooleanValue( $value ),
			ValueType::UnregisteredType => new UnregisteredTypeValue( $propertyType, $value ),
		};
	}

	private function deserializeRelationValue( array $json ): RelationValue {
		$relations = [];

		foreach ( $json as $relation ) {
			$relations[] = new Relation(
				id: new RelationId( $relation['id'] ),
				targetId: $this->subjectIdParser->parseOrThrow( $relation['target'] ),
			);
		}

		return new RelationValue( ...$relations );
	}

}
