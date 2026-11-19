<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\PropertyType\Types\DateTimeType;

/**
 * @covers \AEWI\SWS\Domain\PropertyType\Types\DateTimeType
 */
class DateTimeTypeTest extends TestCase {

	public function testHasNoDisplayAttributes(): void {
		$this->assertSame( [], ( new DateTimeType() )->getDisplayAttributeNames() );
	}

}
