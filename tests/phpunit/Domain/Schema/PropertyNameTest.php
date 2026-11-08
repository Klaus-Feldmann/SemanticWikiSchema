<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Schema\PropertyName
 */
class PropertyNameTest extends TestCase {

	public function testTrimsSurroundingWhitespace(): void {
		$this->assertSame( 'Age', ( new PropertyName( '  Age  ' ) )->text );
	}

	public function testRejectsWhitespaceOnlyName(): void {
		$this->expectException( InvalidArgumentException::class );
		new PropertyName( '   ' );
	}

	public function testRejectsEmptyName(): void {
		$this->expectException( InvalidArgumentException::class );
		new PropertyName( '' );
	}

}
