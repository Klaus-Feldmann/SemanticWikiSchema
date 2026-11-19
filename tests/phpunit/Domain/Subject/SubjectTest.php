<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Domain\Subject;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Tests\Data\TestSubject;

/**
 * @covers \AEWI\SWS\Domain\Subject\Subject
 */
class SubjectTest extends TestCase {

	public function testHasSameIdentity(): void {
		$firstSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );
		$secondSubject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$this->assertTrue( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

	public function testHasSameIdentityWithDifferentId(): void {
		$secondSubject = TestSubject::build( new SubjectId( 's11111111111111' ) );
		$firstSubject = TestSubject::build( new SubjectId( 's11111111111112' ) );

		$this->assertFalse( $firstSubject->hasSameIdentity( $secondSubject ) );
	}

	public function testSetStatementsReplacesTheStatementList(): void {
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$newStatement = new Statement(
			new PropertyName( 'NewProp' ),
			'text',
			new StringValue( 'hello' )
		);
		$newStatementList = new StatementList( [ $newStatement ] );

		$subject->setStatements( $newStatementList );

		$this->assertSame( $newStatementList, $subject->getStatements() );
	}

	public function testWithStatementsKeepsIdLabelAndSchema(): void {
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ) );

		$copy = $subject->withStatements( new StatementList( [] ) );

		$this->assertSame( $subject->getId(), $copy->getId() );
		$this->assertSame( $subject->getLabel(), $copy->getLabel() );
		$this->assertSame( $subject->getSchemaName(), $copy->getSchemaName() );
	}

	public function testWithStatementsLeavesTheOriginalStatementsInPlace(): void {
		$originalStatements = new StatementList( [
			new Statement( new PropertyName( 'Original' ), 'text', new StringValue( 'hello' ) ),
		] );
		$subject = TestSubject::build( new SubjectId( TestSubject::ZERO_GUID ), statements: $originalStatements );

		$copy = $subject->withStatements( new StatementList( [] ) );

		$this->assertSame( $originalStatements, $subject->getStatements() );
		$this->assertSame( [], $copy->getStatements()->asArray() );
	}

}
