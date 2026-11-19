<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\PropertyType\Types\NumberType;

/**
 * @covers \AEWI\SWS\Domain\PropertyType\Types\NumberType
 */
class NumberTypeTest extends TestCase {

	public function testDisplayAttributeNamesContainsPrecision(): void {
		$this->assertSame( [ 'precision' ], ( new NumberType() )->getDisplayAttributeNames() );
	}

}
