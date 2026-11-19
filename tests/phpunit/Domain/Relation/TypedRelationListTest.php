<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Relation;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Relation\TypedRelationList;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Tests\Data\TestRelation;

/**
 * @covers \AEWI\SWS\Domain\Relation\TypedRelationList
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
