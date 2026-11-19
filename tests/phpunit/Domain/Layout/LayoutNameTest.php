<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Layout;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Layout\LayoutName;

/**
 * @covers \AEWI\SWS\Domain\Layout\LayoutName
 */
class LayoutNameTest extends TestCase {

	public function testGetText(): void {
		$layoutName = new LayoutName( 'FinancialOverview' );

		$this->assertSame( 'FinancialOverview', $layoutName->getText() );
	}

	public function testEmptyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new LayoutName( '' );
	}

	public function testWhitespaceOnlyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new LayoutName( '   ' );
	}

}
