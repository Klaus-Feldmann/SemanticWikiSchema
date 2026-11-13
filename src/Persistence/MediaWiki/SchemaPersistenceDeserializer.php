<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\PropertyType\PropertyTypeLookup;
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;

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
			id: $version === 2 ? new \ProfessionalWiki\SWS\Domain\Identity\SchemaId( $json['id'] ?? '' ) : null,
			identity: $version === 2 ? \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity::fromJson( $json ) : null,
			description: $json['description'] ?? '',
			properties: $this->propertiesFromJson( $json ),
			subjectsAreDependent: ( $json['subjectsAreDependent'] ?? false ) === true,
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
