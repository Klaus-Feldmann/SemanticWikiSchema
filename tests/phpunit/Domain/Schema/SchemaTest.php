<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Tests\Data\TestProperty;

/**
 * @covers \AEWI\SWS\Domain\Schema\Schema
 */
class SchemaTest extends TestCase {

	public function testPropertyExists(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildText(),
		] );

		$this->assertTrue( $schema->hasProperty( 'name' ) );
		$this->assertFalse( $schema->hasProperty( 'foo' ) );
	}

	private function newSchemaWithProperties( array $propertyDefinitions ): Schema {
		return new Schema(
			name: new SchemaName( 'Company' ),
			description: 'A company',
			properties: new PropertyDefinitions( $propertyDefinitions ),
			subjectsAreDependent: false
		);
	}

	public function testGetRelationProperties(): void {
		$ceoProperty = TestProperty::buildRelation( description: 'The company CEO', targetSchemas: [ new SchemaName( 'Person' ) ] );
		$barProperty = TestProperty::buildRelation( targetSchemas: [ new SchemaName( 'Tomato' ) ] );

		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildText(),
			'ceo' => $ceoProperty,
			'foo' => TestProperty::buildText(),
			'bar' => $barProperty,
		] );

		$this->assertEquals(
			new PropertyDefinitions( [
				'ceo' => $ceoProperty,
				'bar' => $barProperty,
			] ),
			$schema->getRelationProperties()
		);
	}

}
