<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Persistence\MediaWiki;

use InvalidArgumentException;
use AEWI\SWS\Domain\Schema\PropertyDefinition;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\PropertyType\PropertyTypeLookup;
use AEWI\SWS\Presentation\SchemaPresentationSerializer;

/**
 * MediaWiki revision SPECIFIC deserializer. Not for general use such as in the presentation layer.
 *
 * Related @see SchemaPresentationSerializer
 */
class SchemaPersistenceDeserializer {

	public function __construct(
		private readonly PropertyTypeLookup $propertyTypeLookup,
	) {
	}

	/**
	 * @throws InvalidArgumentException
	 */
	public function deserialize( SchemaName $schemaName, string $json ): Schema {
		$json = json_decode( $json, true );

		if ( !is_array( $json ) ) {
			throw new InvalidArgumentException( 'Invalid JSON' );
		}

		$version = $json['formatVersion'] ?? 1;
		if ( !in_array( $version, [1, 2], true ) ) { throw new InvalidArgumentException( 'Unsupported schema content version' ); }
		return new Schema(
			name: $schemaName,
			id: $version === 2 ? new \AEWI\SWS\Domain\Identity\SchemaId( $json['id'] ?? '' ) : null,
			identity: $version === 2 ? \AEWI\SWS\Domain\Identity\LocalizedIdentity::fromJson( $json ) : null,
			description: $json['description'] ?? '',
			properties: $this->propertiesFromJson( $json ),
			subjectsAreDependent: ( $json['subjectsAreDependent'] ?? false ) === true,
			fieldKeyChanges: \AEWI\SWS\Domain\Schema\FieldKeyChanges::validateHistory( $json['fieldKeyChanges'] ?? [] ),
		);
	}

	/**
	 * Properties of an unregistered type are not dropped here: PropertyDefinition::fromJson
	 * preserves them. Only structurally invalid definitions are skipped.
	 */
	private function propertiesFromJson( array $json ): PropertyDefinitions {
		$properties = [];

		foreach ( $json['propertyDefinitions'] ?? [] as $propertyName => $property ) {
			if ( is_string( $propertyName ) ) {
				try {
					$properties[$propertyName] = PropertyDefinition::fromJson( $property, $this->propertyTypeLookup );
				}
				catch ( InvalidArgumentException ) {
					// TODO: log error
				}
			}
			else {
				// TODO: log error
			}
		}

		return new PropertyDefinitions( $properties );
	}

}
