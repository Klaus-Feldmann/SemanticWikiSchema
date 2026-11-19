<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\SWSExtension;

/**
 * @covers \AEWI\SWS\SWSExtension
 */
class SWSExtensionTest extends TestCase {

	public function testGetInstanceIsSingleton(): void {
		$this->assertSame( SWSExtension::getInstance(), SWSExtension::getInstance() );
	}

	public function testPropertyTypeLookupReturnsNullForUnregisteredType(): void {
		$this->assertNull(
			SWSExtension::getInstance()->getPropertyTypeLookup()->getType( 'does-not-exist' )
		);
	}

}
