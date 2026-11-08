<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Mapping;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Mapping\Mapping;
use ProfessionalWiki\SWS\Domain\Mapping\MappingName;
use ProfessionalWiki\SWS\Domain\Mapping\SchemaMapping;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Mapping\Mapping
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
