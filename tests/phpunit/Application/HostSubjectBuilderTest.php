<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\HostSubject;
use AEWI\SWS\Application\HostSubjectBuilder;
use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectMap;
use AEWI\SWS\Tests\Data\TestNaming;
use AEWI\SWS\Tests\Data\TestRelation;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \AEWI\SWS\Application\HostSubjectBuilder
 * @covers \AEWI\SWS\Application\HostSubject
 */
class HostSubjectBuilderTest extends TestCase {

	private const string PERSON = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const string RECORD = 'sRecord11111111';
	private const string PLACE = 'sSpain111111111';
	private const string PAGE_NAME = 'Pablo Picasso';

	private function newBuilder(): HostSubjectBuilder {
		return TestNaming::newHostSubjectBuilder(
			new InMemorySchemaLookup(
				TestSchema::build( name: 'Person' ),
				TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
				TestSchema::build( name: 'Record', subjectsAreDependent: true ),
				TestSchema::build( name: 'Place' ),
			)
		);
	}

	public function testADependentSubjectIsNamedAfterTheSubjectThatPointsAtIt(): void {
		$birth = $this->subject( self::BIRTH, 'Birth' );

		$this->assertEquals(
			new HostSubject(
				id: self::PERSON,
				schema: 'Person',
				displayName: 'Pablo Picasso',
				displayNameIsGenerated: false,
				hostSubject: null
			),
			$this->newBuilder()->build( $birth, $this->picassoWith( $birth ), self::PAGE_NAME )
		);
	}

	public function testAHostSubjectNobodyNamedIsNamedAfterItsOwnHostSubject(): void {
		$record = $this->subject( self::RECORD, 'Record' );
		$page = $this->picassoWith(
			$this->subject( self::BIRTH, 'Birth', $this->relation( 'Record', self::RECORD ) ),
			$record
		);

		$this->assertEquals(
			new HostSubject(
				id: self::BIRTH,
				schema: 'Birth',
				displayName: 'Birth',
				displayNameIsGenerated: true,
				hostSubject: new HostSubject(
					id: self::PERSON,
					schema: 'Person',
					displayName: 'Pablo Picasso',
					displayNameIsGenerated: false,
					hostSubject: null
				)
			),
			$this->newBuilder()->build( $record, $page, self::PAGE_NAME )
		);
	}

	public function testAHostSubjectThatIsThePagesMainSubjectWithoutALabelIsNamedAfterThePage(): void {
		$birth = $this->subject( self::BIRTH, 'Birth' );
		$page = new PageSubjects(
			TestSubject::build(
				id: self::PERSON,
				label: null,
				schemaName: TestSchema::reference( 'Person' ),
				statements: new StatementList( [ $this->relation( 'Birth', self::BIRTH ) ] )
			),
			new SubjectMap( $birth )
		);

		$this->assertEquals(
			new HostSubject(
				id: self::PERSON,
				schema: 'Person',
				displayName: self::PAGE_NAME,
				displayNameIsGenerated: false,
				hostSubject: null
			),
			$this->newBuilder()->build( $birth, $page, self::PAGE_NAME )
		);
	}

	public function testAStandaloneSubjectIsNotNamedAfterTheSubjectThatPointsAtIt(): void {
		$place = $this->subject( self::PLACE, 'Place' );
		$page = $this->picassoWith(
			$this->subject( self::BIRTH, 'Birth', $this->relation( 'Place', self::PLACE ) ),
			$place
		);

		$this->assertNull( $this->newBuilder()->build( $place, $page, self::PAGE_NAME ) );
	}

	public function testHostSubjectsThatPointAtEachOtherAreNamedOnce(): void {
		$record = $this->subject( self::RECORD, 'Record', $this->relation( 'Birth', self::BIRTH ) );
		$page = new PageSubjects(
			null,
			new SubjectMap( $this->subject( self::BIRTH, 'Birth', $this->relation( 'Record', self::RECORD ) ), $record )
		);

		$this->assertEquals(
			new HostSubject(
				id: self::BIRTH,
				schema: 'Birth',
				displayName: 'Birth',
				displayNameIsGenerated: true,
				hostSubject: null
			),
			$this->newBuilder()->build( $record, $page, self::PAGE_NAME )
		);
	}

	public function testAChainOfHostSubjectsLongerThanTheBoundIsNamedUpToTheBound(): void {
		$records = $this->chainOfRecords( HostSubjectBuilder::MAX_NAMED_HOST_SUBJECTS + 2 );

		$this->assertSame(
			HostSubjectBuilder::MAX_NAMED_HOST_SUBJECTS,
			$this->lengthOf( $this->newBuilder()->build( $records[0], new PageSubjects( null, new SubjectMap( ...$records ) ), self::PAGE_NAME ) )
		);
	}

	/**
	 * @return Subject[] Records each pointed at by the next.
	 */
	private function chainOfRecords( int $length ): array {
		$ids = array_map( static fn ( int $index ): string => 'sRecordChainAA' . 'ABCDEFGHJKLMNPQRSTUVWXYZ'[$index], range( 0, $length - 1 ) );

		return array_map(
			fn ( int $index ): Subject => $index === 0
				? $this->subject( $ids[$index], 'Record' )
				: $this->subject( $ids[$index], 'Record', $this->relation( 'Record', $ids[$index - 1] ) ),
			range( 0, $length - 1 )
		);
	}

	private function lengthOf( ?HostSubject $hostSubject ): int {
		return $hostSubject === null ? 0 : 1 + $this->lengthOf( $hostSubject->hostSubject );
	}

	private function picassoWith( Subject ...$otherSubjects ): PageSubjects {
		return new PageSubjects(
			TestSubject::build(
				id: self::PERSON,
				label: 'Pablo Picasso',
				schemaName: TestSchema::reference( 'Person' ),
				statements: new StatementList( [ $this->relation( 'Birth', self::BIRTH ) ] )
			),
			new SubjectMap( ...$otherSubjects )
		);
	}

	private function subject( string $id, string $schema, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: null,
			schemaName: TestSchema::reference( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

}
