<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\SWS\Application\Validation\SubjectValidator;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestRelation;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\SWS\Application\Validation\DependentSubjectValidator
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
