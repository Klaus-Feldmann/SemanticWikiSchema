<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Queries\GetSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;

/**
 * @covers \ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem
 */
class GetSubjectResponseItemTest extends TestCase {

	public function testFromSubjectCopiesSubjectIdentity(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(
				id: 's1demo1aaaaaaa1',
				label: new SubjectLabel( 'ACME Corp' ),
				schemaName: new SchemaName( 'Organization' ),
			),
			null,
			'ACME Corp',
			null
		);

		$this->assertSame( 's1demo1aaaaaaa1', $item->id );
		$this->assertSame( 'ACME Corp', $item->label );
		$this->assertSame( 'Organization', $item->schema );
	}

	public function testFromSubjectKeepsTheStoredLabelAbsentAndTakesTheDisplayNameGiven(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build( label: null, schemaName: new SchemaName( 'Organization' ) ),
			null,
			// Nobody chose a name, so the Schema supplies one and the item says so.
			null,
			null
		);

		$this->assertNull( $item->label );
		$this->assertSame( 'Organization', $item->displayName );
		$this->assertTrue( $item->displayNameIsGenerated );
	}

	public function testFromSubjectTakesTheGeneratedFlagGiven(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build( label: new SubjectLabel( 'ACME Corp' ) ),
			null,
			'ACME Corp',
			null
		);

		$this->assertFalse( $item->displayNameIsGenerated );
	}

	public function testFromSubjectArrayifiesStatementsByPropertyName(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(
				statements: new StatementList( [
					TestStatement::build( property: 'Animal', value: 'bunny' ),
					TestStatement::build( property: 'Fluff', value: new NumberValue( 9001 ), propertyType: 'number' ),
				] )
			),
			null,
			'ACME Corp',
			null
		);

		$this->assertSame(
			[
				'Animal' => [ 'propertyType' => 'text', 'value' => [ 'bunny' ] ],
				'Fluff' => [ 'propertyType' => 'number', 'value' => 9001 ],
			],
			$item->statements
		);
	}

	public function testFromSubjectWithoutPageIdentifiersLeavesPageFieldsNull(): void {
		$item = GetSubjectResponseItem::fromSubject( TestSubject::build(), null, 'Test subject', null );

		$this->assertNull( $item->pageId );
		$this->assertNull( $item->pageTitle );
		$this->assertNull( $item->pageNamespaceId );
	}

	public function testFromSubjectCopiesPageIdentifiers(): void {
		$item = GetSubjectResponseItem::fromSubject(
			TestSubject::build(),
			new PageIdentifiers( new PageId( 42 ), 'Help:Bunnies', 12 ),
			'Test subject',
			null
		);

		$this->assertSame( 42, $item->pageId );
		$this->assertSame( 'Help:Bunnies', $item->pageTitle );
		$this->assertSame( 12, $item->pageNamespaceId );
	}

}
