<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateTimeType;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\DateTimeType
 */
class DateTimeTypeTest extends TestCase {

	public function testHasNoDisplayAttributes(): void {
		$this->assertSame( [], ( new DateTimeType() )->getDisplayAttributeNames() );
	}

}
