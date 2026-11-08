<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\SWSExtension;

/**
 * @covers \ProfessionalWiki\SWS\SWSExtension
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
