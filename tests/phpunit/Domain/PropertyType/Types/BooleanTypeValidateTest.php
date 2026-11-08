<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\BooleanType;
use ProfessionalWiki\SWS\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\SWS\Domain\Schema\PropertyCore;
use ProfessionalWiki\SWS\Domain\Value\BooleanValue;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\BooleanType
 */
class BooleanTypeValidateTest extends TestCase {

	private BooleanType $type;

	protected function setUp(): void {
		$this->type = new BooleanType();
	}

	public function testRequiredAndMissingReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new NumberValue( 0 ),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testRequiredAndBooleanValuePresentReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new BooleanValue( true ),
			$this->newProperty( required: true ),
		) );

		$this->assertSame( [], $this->type->validate(
			new BooleanValue( false ),
			$this->newProperty( required: true ),
		) );
	}

	public function testOptionalAndMissingReturnsNoViolations(): void {
		$this->assertSame( [], $this->type->validate(
			new NumberValue( 0 ),
			$this->newProperty( required: false ),
		) );
	}

	private function newProperty( bool $required ): BooleanProperty {
		return BooleanProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[],
		);
	}

}
