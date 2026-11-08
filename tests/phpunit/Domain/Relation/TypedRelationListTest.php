<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\Relation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\Relation\TypedRelationList;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\SWS\Domain\Relation\TypedRelationList
 */
class TypedRelationListTest extends TestCase {

	public function testGetIdsAsStringArray(): void {
		$this->assertSame(
			[
				'r11111111111113',
				'r11111111111114'
			],
			( new TypedRelationList( [
				TestRelation::build( id: 'r11111111111113' )->withPropertyName( new PropertyName( 'Friends' ) ),
				TestRelation::build( id: 'r11111111111114' )->withPropertyName( new PropertyName( 'Friends' ) ),
			] ) )->getIdsAsStringArray()
		);
	}

}
