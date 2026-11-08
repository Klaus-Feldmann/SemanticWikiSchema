<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\SWS\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\SWS\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType
 */
class SelectTypeTest extends TestCase {

	public function testEveryChosenOptionContributesItsLabel(): void {
		$this->assertSame(
			[ 'In review', 'Published' ],
			( new SelectType() )->searchText( new StringValue( 'o2', 'o3' ), $this->statusProperty() )
		);
	}

	public function testIdTheSchemaNoLongerOffersIsSkipped(): void {
		$this->assertSame(
			[ 'Draft' ],
			( new SelectType() )->searchText( new StringValue( 'o1', 'o9' ), $this->statusProperty() )
		);
	}

	public function testValueWithoutADefinitionHasNoSearchText(): void {
		$this->assertSame( [], ( new SelectType() )->searchText( new StringValue( 'o2' ), null ) );
	}

	private function statusProperty(): SelectProperty {
		return TestProperty::buildSelect( options: [
			new SelectOption( 'o1', 'Draft' ),
			new SelectOption( 'o2', 'In review' ),
			new SelectOption( 'o3', 'Published' ),
			new SelectOption( 'o4', 'Archived' ),
		] );
	}

}
