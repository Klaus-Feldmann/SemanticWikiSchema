<?php

declare( strict_types = 1 );
namespace ProfessionalWiki\SWS\Tests\Domain\Schema;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\SWS\Tests\Data\TestSources;

class ImageSourcesTest extends TestCase {
	public function testFourSourcesRoundTripAndValidate(): void {
		foreach ( [ 'wikidata', 'wikibase-local', 'local', 'commons' ] as $source ) {
			$json = [ 'type' => 'image', 'imageSource' => $source ];
			if ( in_array( $source, [ 'wikidata', 'wikibase-local' ], true ) ) { $json['externalProperty'] = [ 'source' => $source, 'id' => 'P18' ]; }
			$property = PropertyDefinition::fromJson( $json, TestSources::newPropertyTypeRegistry() );
			$this->assertSame( $source, $property->toJson()['imageSource'] );
			$this->assertSame( $json['externalProperty'] ?? null, $property->toJson()['externalProperty'] ?? null );
			$this->assertTrue( SchemaContentValidator::newInstance()->validate( json_encode( [ 'propertyDefinitions' => [ 'Bild' => $property->toJson() ] ] ) ) );
		}
	}
	public function testLegacyImageBindingInfersCorrectSource(): void {
		$property = PropertyDefinition::fromJson( [ 'type' => 'image', 'externalProperty' => [ 'source' => 'wikibase-local', 'id' => 'P12' ] ], TestSources::newPropertyTypeRegistry() );
		$this->assertSame( 'wikibase-local', $property->toJson()['imageSource'] );
	}
	public function testInvalidSourceRejectedByDomainAndSchemaValidator(): void {
		$json = [ 'type' => 'image', 'imageSource' => 'external' ];
		$this->assertFalse( SchemaContentValidator::newInstance()->validate( json_encode( [ 'propertyDefinitions' => [ 'Bild' => $json ] ] ) ) );
		$this->expectException( \InvalidArgumentException::class );
		PropertyDefinition::fromJson( $json, TestSources::newPropertyTypeRegistry() );
	}
}
