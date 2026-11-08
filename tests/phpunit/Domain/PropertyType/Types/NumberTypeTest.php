<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\NumberType;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\NumberType
 */
class NumberTypeTest extends TestCase {

	public function testDisplayAttributeNamesContainsPrecision(): void {
		$this->assertSame( [ 'precision' ], ( new NumberType() )->getDisplayAttributeNames() );
	}

}
