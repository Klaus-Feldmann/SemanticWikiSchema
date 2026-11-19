<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\PropertyType\Types\BooleanType;
use AEWI\SWS\Domain\Schema\Property\BooleanProperty;
use AEWI\SWS\Domain\Schema\PropertyCore;
use AEWI\SWS\Domain\Value\BooleanValue;
use AEWI\SWS\Domain\Value\NumberValue;

/**
 * @covers \AEWI\SWS\Domain\PropertyType\Types\BooleanType
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
