<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Validation\DependentSubjectValidator;
use AEWI\SWS\Application\Validation\ProposedSubjectValidator;
use AEWI\SWS\Application\Validation\SubjectValidator;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Subject\SubjectMap;
use AEWI\SWS\Domain\Validation\Severity;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Tests\Data\TestProperty;
use AEWI\SWS\Tests\Data\TestRelation;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\Data\TestSources;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use AEWI\SWS\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \AEWI\SWS\Application\Validation\DependentSubjectValidator
 */
class DependentSubjectValidatorTest extends TestCase {

	private const string HOST = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const string RECORD = 'sRecord11111111';

	private function newValidator(): DependentSubjectValidator {
		return new DependentSubjectValidator( new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( new InMemorySchemaLookup(
				TestSchema::build( name: 'Person' ),
				TestSchema::build(
					name: 'Birth',
					properties: new PropertyDefinitions( [
						'Date' => TestProperty::buildText( required: true, constraintSeverities: [ 'required' => Severity::Error ] ),
						'Record' => TestProperty::buildRelation( targetSchemas: [ 'Record' ] ),
						'Child' => TestProperty::buildRelation( targetSchemas: [ 'Person' ] ),
					] ),
					subjectsAreDependent: true
				),
				TestSchema::build( name: 'Record', subjectsAreDependent: true )
			) ),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
		) );
	}

	private function host(): Subject {
		return TestSubject::build( id: self::HOST, schemaName: TestSchema::reference( 'Person' ) );
	}

	private function birth( Statement ...$statements ): Subject {
		return TestSubject::build(
			id: self::BIRTH,
			label: null,
			schemaName: TestSchema::reference( 'Birth' ),
			statements: new StatementList( $statements )
		);
	}

	private function dated(): Statement {
		return TestStatement::build( property: 'Date', value: '1881-10-25' );
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

	/**
	 * @return string[]
	 */
	private function codesOf( array $violations ): array {
		return array_map( static fn ( Violation $violation ): string => $violation->code, $violations );
	}

	public function testEachViolationNamesTheDependentSubjectItIsOn(): void {
		$violations = $this->newValidator()->validate( new SubjectMap( $this->birth() ), $this->host(), new SubjectMap() );

		$this->assertEquals( [ new SubjectId( self::BIRTH ) ], array_map( static fn ( Violation $v ) => $v->subjectId, $violations->all ) );
	}

	public function testAnotherDependentSubjectOfTheWriteCountsAsAnExistingRelationTarget(): void {
		$record = TestSubject::build( id: self::RECORD, label: null, schemaName: TestSchema::reference( 'Record' ) );

		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Record', self::RECORD ) ), $record ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->all );
	}

	public function testTheHostSubjectCountsAsAnExistingRelationTarget(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Child', self::HOST ) ) ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->all );
	}

	public function testABlockingViolationOfANewDependentSubjectIsNew(): void {
		$violations = $this->newValidator()->validate( new SubjectMap( $this->birth() ), $this->host(), new SubjectMap() );

		$this->assertSame( [ 'required' ], $this->codesOf( $violations->newBlocking ) );
	}

	public function testAWarningOfADependentSubjectDoesNotBlock(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Record', 'sMissing1111111' ) ) ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->newBlocking );
	}

	public function testAViolationTheStoredDependentSubjectAlreadyHasIsNotNew(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth() ),
			$this->host(),
			new SubjectMap( $this->birth() )
		);

		$this->assertSame( [], $violations->newBlocking );
	}

}
