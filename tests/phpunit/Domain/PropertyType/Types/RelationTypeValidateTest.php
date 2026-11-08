<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use ProfessionalWiki\SWS\Tests\Data\TestSources;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\RelationType
 */
class RelationTypeValidateTest extends TestCase {

	private RelationType $type;

	protected function setUp(): void {
		$this->type = new RelationType( TestSources::newSchemaReferenceParser() );
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate( new RelationValue(), $this->newProperty( required: false ) );

		$this->assertSame( [], $violations );
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate( new RelationValue(), $this->newProperty( required: true ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testRequiredAndNonRelationValueReturnsRequired(): void {
		$violations = $this->type->validate( new NumberValue( 42 ), $this->newProperty( required: true ) );

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testRequiredAndNonEmptyReturnsNoViolations(): void {
		$relation = new Relation(
			id: new RelationId( 'r123456789abcde' ),
			targetId: new SubjectId( 's12345678abcdef' ),
		);

		$violations = $this->type->validate( new RelationValue( $relation ), $this->newProperty( required: true ) );

		$this->assertSame( [], $violations );
	}

	private function newProperty( bool $required ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'targetSchemas' => [ 'Person' ], 'multiple' => false ],
			TestSources::newSchemaReferenceParser(),
		);
	}

}
