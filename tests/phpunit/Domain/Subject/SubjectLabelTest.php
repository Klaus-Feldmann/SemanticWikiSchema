<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Subject\SubjectLabel
 */
class SubjectLabelTest extends TestCase {

	public function testStoresText(): void {
		$label = new SubjectLabel( 'hello' );

		$this->assertSame( 'hello', $label->text );
	}

	public function testSurroundingWhitespaceIsPreserved(): void {
		$label = new SubjectLabel( '  foo  ' );

		$this->assertSame( '  foo  ', $label->text );
	}

	public function testAcceptsEmptyString(): void {
		$label = new SubjectLabel( '' );

		$this->assertSame( '', $label->text );
	}

	public function testAcceptsWhitespaceOnlyString(): void {
		$label = new SubjectLabel( "  \t\n  " );

		$this->assertSame( "  \t\n  ", $label->text );
	}

	/**
	 * @dataProvider textThatMeansNoLabelProvider
	 */
	public function testFromTextReadsBlankTextAsNoLabel( ?string $text ): void {
		$this->assertNull( SubjectLabel::fromText( $text ) );
	}

	public static function textThatMeansNoLabelProvider(): iterable {
		yield 'null' => [ null ];
		yield 'empty' => [ '' ];
		yield 'whitespace only' => [ "  \t\n  " ];
	}

	public function testFromTextKeepsALabelVerbatim(): void {
		$this->assertSame( '  foo  ', SubjectLabel::fromText( '  foo  ' )?->text );
	}

}
