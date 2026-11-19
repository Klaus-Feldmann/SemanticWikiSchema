<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\PropertyType\Types\TextType;

/**
 * @covers \AEWI\SWS\Domain\PropertyType\Types\TextType
 */
class TextTypeTest extends TestCase {

	public function testDisplayAttributeNamesIsEmpty(): void {
		$this->assertSame( [], ( new TextType() )->getDisplayAttributeNames() );
	}

}
