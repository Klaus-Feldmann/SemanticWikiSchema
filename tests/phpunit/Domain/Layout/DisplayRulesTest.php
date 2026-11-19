<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Layout;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Layout\DisplayRule;
use AEWI\SWS\Domain\Layout\DisplayRules;

/**
 * @covers \AEWI\SWS\Domain\Layout\DisplayRules
 */
class DisplayRulesTest extends TestCase {

	public function testEmptyCollectionIsEmpty(): void {
		$rules = new DisplayRules( [] );

		$this->assertTrue( $rules->isEmpty() );
	}

	public function testNonEmptyCollectionIsNotEmpty(): void {
		$rules = new DisplayRules( [
			new DisplayRule( new PropertyName( 'Revenue' ), [] ),
		] );

		$this->assertFalse( $rules->isEmpty() );
	}

	public function testIterationPreservesOrder(): void {
		$first = new DisplayRule( new PropertyName( 'Revenue' ), [] );
		$second = new DisplayRule( new PropertyName( 'Profit' ), [ 'precision' => 2 ] );
		$third = new DisplayRule( new PropertyName( 'Assets' ), [] );

		$rules = new DisplayRules( [ $first, $second, $third ] );

		$this->assertSame( [ $first, $second, $third ], iterator_to_array( $rules ) );
	}

}
