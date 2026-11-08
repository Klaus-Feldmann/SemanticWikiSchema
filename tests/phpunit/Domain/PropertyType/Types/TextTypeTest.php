<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\TextType
 */
class TextTypeTest extends TestCase {

	public function testDisplayAttributeNamesIsEmpty(): void {
		$this->assertSame( [], ( new TextType() )->getDisplayAttributeNames() );
	}

}
