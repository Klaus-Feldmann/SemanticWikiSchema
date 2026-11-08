<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Schema;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Schema\SchemaName
 */
class SchemaNameTest extends TestCase {

	public function testNonReservedSchemaNameIsValid(): void {
		$schemaName = new SchemaName( 'FooBar' );

		$this->assertSame( 'FooBar', $schemaName->getText() );
	}

	public function testEmptySchemaNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new SchemaName( '' );
	}

	/**
	 * @dataProvider reservedSchemaNameProvider
	 */
	public function testReservedSchemaNameIsInvalid( string $name ): void {
		$this->expectException( InvalidArgumentException::class );

		new SchemaName( $name );
	}

	public static function reservedSchemaNameProvider(): iterable {
		yield [ 'Page' ];
		yield [ 'Subject' ];
		yield [ 'pAgE' ];
		yield [ 'sUbJeCt' ];
	}

}
