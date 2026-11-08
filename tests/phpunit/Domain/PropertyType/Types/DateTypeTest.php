<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\DateType;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\DateType
 */
class DateTypeTest extends TestCase {

	public function testHasNoDisplayAttributes(): void {
		$this->assertSame( [], ( new DateType() )->getDisplayAttributeNames() );
	}

}
