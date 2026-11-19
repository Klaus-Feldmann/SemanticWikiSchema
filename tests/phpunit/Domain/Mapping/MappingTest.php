<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Mapping;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Mapping\Mapping;
use AEWI\SWS\Domain\Mapping\MappingName;
use AEWI\SWS\Domain\Mapping\SchemaMapping;

/**
 * @covers \AEWI\SWS\Domain\Mapping\Mapping
 */
class MappingTest extends TestCase {

	public function testSchemaNameOfDigitsAloneIsAString(): void {
		$mapping = new Mapping(
			new MappingName( 'EDM' ),
			[],
			[ 'Museum' => new SchemaMapping( subject: null ), '1984' => new SchemaMapping( subject: null ) ]
		);

		$this->assertSame( [ 'Museum', '1984' ], $mapping->getSchemaNames() );
	}

}
