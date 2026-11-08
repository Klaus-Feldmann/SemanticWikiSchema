<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\SWS\Tests\Data\TestSources;

class ExternalValueLinksTest extends TestCase {
	public function testTargetsSurviveSerializationAndSchemaValidation(): void {
		foreach ( [ 'source', 'local' ] as $target ) {
			$json = [ 'type' => 'text', 'wikiLink' => [ 'namespace' => 'Werk', 'values' => false,
				'externalTarget' => $target ], 'externalProperty' => [ 'source' => 'wikidata', 'id' => 'P31' ] ];
			$property = PropertyDefinition::fromJson( $json, TestSources::newPropertyTypeRegistry() );
			$this->assertSame( $json['wikiLink'], $property->toJson()['wikiLink'] );
			$this->assertTrue( SchemaContentValidator::newInstance()->validate( json_encode( [ 'propertyDefinitions' => [ 'Test' => $json ] ] ) ) );
		}
	}

	public function testOldSchemasDoNotGainAnExplicitTarget(): void {
		$property = PropertyDefinition::fromJson( [ 'type' => 'text', 'wikiLink' => [ 'label' => false ] ], TestSources::newPropertyTypeRegistry() );
		$this->assertSame( [ 'label' => false ], $property->toJson()['wikiLink'] );
	}

	/** @dataProvider invalidTargets */
	public function testInvalidTargetsFailBothValidators( mixed $target ): void {
		$json = [ 'type' => 'text', 'wikiLink' => [ 'externalTarget' => $target ] ];
		$this->assertFalse( SchemaContentValidator::newInstance()->validate( json_encode( [ 'propertyDefinitions' => [ 'Test' => $json ] ] ) ) );
		$this->expectException( InvalidArgumentException::class );
		PropertyDefinition::fromJson( $json, TestSources::newPropertyTypeRegistry() );
	}

	public static function invalidTargets(): array {
		return [ [ 'unknown' ], [ '' ], [ null ], [ false ], [ [] ] ];
	}
}
