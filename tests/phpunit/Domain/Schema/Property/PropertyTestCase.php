<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema\Property;

use ProfessionalWiki\SWS\Tests\Data\TestSources;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;

abstract class PropertyTestCase extends TestCase {

	protected function deserializeAndReserialize( string $serialization ): string {
		return json_encode(
			$this->fromJson( $serialization )->toJson(),
			JSON_PRETTY_PRINT
		);
	}

	protected function fromJson( string $json ): PropertyDefinition {
		return PropertyDefinition::fromJson(
			json_decode( $json, true ),
			TestSources::newPropertyTypeRegistry()
		);
	}

	protected function assertSerializationDoesNotChange( string $serialization ): void {
		$this->assertJsonStringEqualsJsonString(
			$serialization,
			$this->deserializeAndReserialize( $serialization )
		);
	}

}
