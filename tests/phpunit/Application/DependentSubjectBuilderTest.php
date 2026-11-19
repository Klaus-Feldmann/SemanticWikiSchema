<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\DependentSubjectBuilder;
use AEWI\SWS\Application\NewSubjectIdResolver;
use AEWI\SWS\Application\StatementListBuilder;
use AEWI\SWS\Application\Subject\Exception\SubjectIdInUseException;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\Domain\Page\PageIdentifiers;
use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Subject\SubjectLabel;
use AEWI\SWS\Domain\Subject\SubjectMap;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Tests\Data\TestNaming;
use AEWI\SWS\Tests\Data\TestRelation;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\Data\TestSources;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\Data\TestSubjectIds;
use AEWI\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use AEWI\SWS\Tests\TestDoubles\StubIdGenerator;

/**
 * @covers \AEWI\SWS\Application\DependentSubjectBuilder
 */
class DependentSubjectBuilderTest extends TestCase {

	private const string HOST = 'sPerson11111111';
	private const string OTHER_HOST = 'sPerson22222222';
	private const string BIRTH = 'sBirth111111111';
	private const string OTHER_BIRTH = 'sBirth222222222';

	private InMemoryPageIdentifiersLookup $pageIdentifiersLookup;
	private DependentSubjectBuilder $builder;

	protected function setUp(): void {
		$this->pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$this->builder = $this->newBuilder();
	}

	private function newBuilder(): DependentSubjectBuilder {
		$schemaLookup = new InMemorySchemaLookup(
			TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
			TestSchema::build( name: 'Name', subjectsAreDependent: true ),
			TestSchema::build( name: 'Place' )
		);

		return new DependentSubjectBuilder(
			newSubjectIdResolver: new NewSubjectIdResolver(
				subjectIdParser: TestSubjectIds::newParser(),
				idGenerator: new StubIdGenerator( '11111111111111' ),
				pageIdentifiersLookup: $this->pageIdentifiersLookup,
			),
			schemaReferenceParser: TestSources::newSchemaReferenceParser(),
			schemaResolver: TestSources::newSchemaResolver( $schemaLookup ),
			statementListBuilder: new StatementListBuilder(
				TestSources::newPropertyTypeRegistry(),
				new StubIdGenerator( '11111111111111' ),
				TestSubjectIds::newParser()
			),
			dependentSubjectRules: TestNaming::newDependentSubjectRules( $schemaLookup ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function birthItem( string $id = self::BIRTH ): array {
		return [
			'id' => $id,
			'schema' => 'Birth',
			'statements' => [ 'Date' => [ 'propertyType' => 'text', 'value' => [ '1881-10-25' ] ] ],
		];
	}

	private function build( array $items, PageSubjects $pageSubjects = new PageSubjects( null, new SubjectMap() ) ): SubjectMap {
		return $this->builder->build( $items, new SubjectId( self::HOST ), $pageSubjects );
	}

	public function testBuildsASubjectWithoutLabelFromAnItem(): void {
		$this->assertEquals(
			new SubjectMap( TestSubject::build(
				id: self::BIRTH,
				label: null,
				schemaName: TestSchema::reference( 'Birth' ),
				statements: new StatementList( [ TestStatement::build( property: 'Date', value: new StringValue( '1881-10-25' ) ) ] )
			) ),
			$this->build( [ $this->birthItem() ] )
		);
	}

	public function testKeepsALabelSoThatTheRulesForDependentSubjectsReportIt(): void {
		$item = $this->birthItem() + [ 'label' => 'Birth of Pablo Picasso' ];

		$this->assertEquals(
			new SubjectLabel( 'Birth of Pablo Picasso' ),
			$this->build( [ $item ] )->getSubject( new SubjectId( self::BIRTH ) )->getLabel()
		);
	}

	/**
	 * @dataProvider malformedItemProvider
	 */
	public function testRefusesAMalformedItem( mixed $item ): void {
		$this->expectException( InvalidArgumentException::class );

		$this->build( [ $item ] );
	}

	public static function malformedItemProvider(): iterable {
		yield 'not an object' => [ 'sBirth111111111' ];
		yield 'no id' => [ [ 'schema' => 'Birth', 'statements' => [] ] ];
		yield 'no schema' => [ [ 'id' => self::BIRTH, 'statements' => [] ] ];
		yield 'no statements' => [ [ 'id' => self::BIRTH, 'schema' => 'Birth' ] ];
		yield 'a label that is not a string' => [ [ 'id' => self::BIRTH, 'schema' => 'Birth', 'statements' => [], 'label' => 42 ] ];
		yield 'a malformed id' => [ [ 'id' => 'not an id', 'schema' => 'Birth', 'statements' => [] ] ];
		yield 'an id of another Source' => [ [ 'id' => 'otherwiki:Q42', 'schema' => 'Birth', 'statements' => [] ] ];
	}

	public function testRefusesASchemaThatDoesNotDeclareItsSubjectsDependent(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->build( [ [ 'id' => self::BIRTH, 'schema' => 'Place', 'statements' => [] ] ] );
	}

	public function testRefusesASchemaThatDoesNotResolve(): void {
		$this->expectException( InvalidArgumentException::class );

		$this->build( [ [ 'id' => self::BIRTH, 'schema' => 'Missing', 'statements' => [] ] ] );
	}

	public function testRefusesAnIdTwice(): void {
		$items = [ $this->birthItem(), $this->birthItem() ];

		$this->expectException( InvalidArgumentException::class );

		$this->build( $items );
	}

	public function testRefusesTheIdOfTheHostSubject(): void {
		$items = [ $this->birthItem( self::HOST ) ];

		$this->expectException( InvalidArgumentException::class );

		$this->build( $items );
	}

	public function testRefusesTheIdOfASubjectOnAnotherPage(): void {
		$this->pageIdentifiersLookup->addIdentifiers(
			new SubjectId( self::OTHER_BIRTH ),
			new PageIdentifiers( new PageId( 2 ), 'Another page', 0 )
		);
		$items = [ $this->birthItem(), $this->birthItem( self::OTHER_BIRTH ) ];

		$this->expectException( SubjectIdInUseException::class );

		$this->build( $items );
	}

	public function testRefusesTheIdOfASubjectOfAnotherSchemaThatTheHostSubjectReaches(): void {
		$page = $this->pageWhereSubjectPointsAt( self::HOST, $this->subjectOnPage( 'Name' ) );
		$items = [ $this->birthItem() ];

		$this->expectException( SubjectIdInUseException::class );

		$this->build( $items, $page );
	}

	public function testTakesTheIdOfADependentSubjectTheHostSubjectReaches(): void {
		$this->pageIdentifiersLookup->addIdentifiers(
			new SubjectId( self::BIRTH ),
			new PageIdentifiers( new PageId( 1 ), 'Pablo Picasso', 0 )
		);

		$this->assertSame(
			[ self::BIRTH ],
			$this->build(
				[ $this->birthItem() ],
				$this->pageWhereSubjectPointsAt( self::HOST, $this->subjectOnPage( 'Birth' ) )
			)->getIdsAsTextArray()
		);
	}

	public function testRefusesTheIdOfADependentSubjectTheHostSubjectDoesNotReach(): void {
		$page = $this->pageWhereSubjectPointsAt( self::OTHER_HOST, $this->subjectOnPage( 'Birth' ) );
		$items = [ $this->birthItem() ];

		$this->expectException( SubjectIdInUseException::class );

		$this->build( $items, $page );
	}

	private function subjectOnPage( string $schema ): Subject {
		return TestSubject::build( id: self::BIRTH, label: null, schemaName: TestSchema::reference( $schema ) );
	}

	private function pageWhereSubjectPointsAt( string $subjectId, Subject $target ): PageSubjects {
		return new PageSubjects(
			null,
			new SubjectMap(
				TestSubject::build(
					id: $subjectId,
					statements: new StatementList( [ TestStatement::buildRelation( 'Birth', [ TestRelation::build( targetId: $target->id->text ) ] ) ] )
				),
				$target
			)
		);
	}

}
