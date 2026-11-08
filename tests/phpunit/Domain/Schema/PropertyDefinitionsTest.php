<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions
 */
class PropertyDefinitionsTest extends TestCase {

	public function testFilter(): void {
		$properties = [
			'p1' => TestProperty::buildText( description: 'foo' ),
			'p2' => TestProperty::buildText( description: 'bar' ),
			'p3' => TestProperty::buildText( description: 'foo' ),
			'p4' => TestProperty::buildText( description: 'bar' ),
		];

		$propertyDefinitions = new PropertyDefinitions( $properties );

		$filteredProperties = $propertyDefinitions->filter( function( PropertyDefinition $property ) {
			return $property->getDescription() === 'foo';
		} );

		$this->assertTrue( $filteredProperties->hasProperty( 'p1' ) );
		$this->assertFalse( $filteredProperties->hasProperty( 'p2' ) );
		$this->assertTrue( $filteredProperties->hasProperty( 'p3' ) );
		$this->assertFalse( $filteredProperties->hasProperty( 'p4' ) );
	}

}
