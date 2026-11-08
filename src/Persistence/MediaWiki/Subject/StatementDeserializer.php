<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki\Subject;

use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdParser;
use ProfessionalWiki\SWS\Domain\Value\BooleanValue;
use ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\UrlValue;
use ProfessionalWiki\SWS\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\SWS\Domain\Value\ValueType;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup;

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
