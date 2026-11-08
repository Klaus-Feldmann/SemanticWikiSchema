<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\ExternalProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Tests\Data\TestSources;

class ExternalPropertyTest extends TestCase {
	public function testReferencesSurvivePropertyJsonRoundTrips(): void {
		foreach ( [ 'wikidata', 'wikibase-local' ] as $source ) {
			foreach ( [ 'text', 'historicalDate', 'select', 'unknown-extension-type' ] as $type ) {
				$json = [ 'type' => $type, 'externalProperty' => [ 'source' => $source, 'id' => 'P31' ] ];
				$property = PropertyDefinition::fromJson( $json, TestSources::newPropertyTypeRegistry() );
				$this->assertSame( $json['externalProperty'], $property->toJson()['externalProperty'] );
				$this->assertSame( $source, $property->getExternalProperty()->source );
				$this->assertSame( 'P31', $property->getExternalProperty()->id );
			}
		}
	}

	public function testPlainTextStaysPlainText(): void {
		$property = PropertyDefinition::fromJson( [ 'type' => 'text' ], TestSources::newPropertyTypeRegistry() );
		$this->assertNull( $property->getExternalProperty() );
		$this->assertArrayNotHasKey( 'externalProperty', $property->toJson() );
	}

	public function testSchemaContentValidationKnowsTheReferenceShape(): void {
		$validator = \ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator::newInstance();
		foreach ( [ 'wikidata', 'wikibase-local' ] as $source ) {
			$this->assertTrue( $validator->validate( json_encode( [ 'propertyDefinitions' => [
				'Test' => [ 'type' => 'text', 'externalProperty' => [ 'source' => $source, 'id' => 'P31' ] ],
			] ] ) ) );
		}
		$this->assertFalse( $validator->validate( json_encode( [ 'propertyDefinitions' => [
			'Test' => [ 'type' => 'text', 'externalProperty' => [ 'source' => 'other', 'id' => 'Q5' ] ],
		] ] ) ) );
	}

	/** @dataProvider invalidReferences */
	public function testRejectsInvalidOrAmbiguousReferences( mixed $value ): void {
		$this->expectException( InvalidArgumentException::class );
		ExternalProperty::fromJson( $value );
	}

	public static function invalidReferences(): array {
		return array_map( static fn ( $value ) => [ $value ], [
			null, 'P31', [], [ 'source' => 'wikidata' ], [ 'id' => 'P31' ],
			[ 'source' => 'other', 'id' => 'P31' ], [ 'source' => 'wikidata', 'id' => 'Q31' ],
			[ 'source' => 'wikidata', 'id' => 'P0' ], [ 'source' => 'wikidata', 'id' => "P31\n" ],
			[ 'source' => 'wikidata', 'id' => ' P31' ], [ 'source' => 'wikidata', 'id' => 'p31' ],
			[ 'source' => 'wikidata', 'id' => 'P31', 'label' => 'mutable label' ],
		] );
	}
}
