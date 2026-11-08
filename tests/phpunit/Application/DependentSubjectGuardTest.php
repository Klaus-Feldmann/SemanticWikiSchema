<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\DependentSubjectGuard;
use ProfessionalWiki\SWS\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\SWS\Domain\Page\DependentSubjectRules;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
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
 * @covers \ProfessionalWiki\SWS\Application\DependentSubjectGuard
 */
class DependentSubjectGuardTest extends TestCase {

	private const string PERSON = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const string RECORD = 'sRecord11111111';
	private const string BIRTH_ELSEWHERE = 'sBirth222222222';

	private function newGuard( Subject ...$subjectsOnOtherPages ): DependentSubjectGuard {
		return new DependentSubjectGuard(
			new DependentSubjectRules( TestSources::newSchemaResolver( new InMemorySchemaLookup(
				TestSchema::build(
					name: 'Person',
					properties: new PropertyDefinitions( [
						'Birth' => TestProperty::buildRelation( targetSchemas: [ 'Birth' ] ),
						'Friend' => TestProperty::buildRelation( targetSchemas: [ 'Person' ] ),
					] )
				),
				TestSchema::build(
					name: 'Birth',
					properties: new PropertyDefinitions( [
						'Record' => TestProperty::buildRelation( targetSchemas: [ 'Record' ] ),
					] ),
					subjectsAreDependent: true
				),
				TestSchema::build( name: 'Record', subjectsAreDependent: true )
			) ) ),
			fn (): InMemorySubjectLookup => new InMemorySubjectLookup( ...$subjectsOnOtherPages )
		);
	}

	private function subject( string $id, string $schema, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: $schema === 'Person' ? 'Pablo Picasso' : null,
			schemaName: TestSchema::reference( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

	private function page( Subject ...$subjects ): PageSubjects {
		return new PageSubjects( null, new SubjectMap( ...$subjects ) );
	}

	/**
	 * @return string[]
	 */
	private function refusalCodes( DependentSubjectGuard $guard, PageSubjects $before, PageSubjects $after ): array {
		try {
			$guard->admit( $before, $after, new SubjectIdList( [] ) );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			return array_map( static fn ( Violation $violation ): string => $violation->code, $exception->violations );
		}

		return [];
	}

	public function testRefusesAWriteThatBreaksARule(): void {
		$after = $this->page( $this->subject( self::BIRTH, 'Birth' ) );

		$this->assertSame(
			[ DependentSubjectRules::WITHOUT_HOST ],
			$this->refusalCodes( $this->newGuard(), PageSubjects::newEmpty(), $after )
		);
	}

	public function testAdmitsThePageWithoutTheDependentSubjectsItLeavesUnreached(): void {
		$before = $this->page(
			$this->subject( self::PERSON, 'Person', $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth' )
		);
		$after = $this->page(
			$this->subject( self::PERSON, 'Person' ),
			$this->subject( self::BIRTH, 'Birth' )
		);

		$this->assertSame(
			[ self::PERSON ],
			$this->newGuard()->admit( $before, $after, new SubjectIdList( [] ) )->getAllSubjects()->getIdsAsTextArray()
		);
	}

	private function pageWithBirthAndRecord(): PageSubjects {
		return $this->page(
			$this->subject( self::PERSON, 'Person', $this->relation( 'Birth', self::BIRTH ) ),
			$this->subject( self::BIRTH, 'Birth', $this->relation( 'Record', self::RECORD ) ),
			$this->subject( self::RECORD, 'Record' )
		);
	}

	public function testAdmitsRemovingADependentSubjectWithoutTheRelationToIt(): void {
		$before = $this->pageWithBirthAndRecord();

		$person = $this->newGuard()->admit( $before, $before->without( new SubjectId( self::BIRTH ) ), new SubjectIdList( [] ) )
			->getAllSubjects()->getSubject( new SubjectId( self::PERSON ) );

		$this->assertSame( [], $person->getReferencedSubjects()->asStringArray() );
	}

	public function testAdmitsRemovingADependentSubjectWithoutTheDependentSubjectsItHosts(): void {
		$before = $this->pageWithBirthAndRecord();

		$this->assertSame(
			[ self::PERSON ],
			$this->newGuard()->admit( $before, $before->without( new SubjectId( self::BIRTH ) ), new SubjectIdList( [] ) )
				->getAllSubjects()->getIdsAsTextArray()
		);
	}

	public function testRefusesANewRelationToADependentSubjectOnAnotherPage(): void {
		$after = $this->page( $this->subject( self::PERSON, 'Person', $this->relation( 'Friend', self::BIRTH_ELSEWHERE ) ) );

		$this->assertSame(
			[ DependentSubjectRules::RELATION_TO_DEPENDENT_SUBJECT ],
			$this->refusalCodes(
				$this->newGuard( $this->subject( self::BIRTH_ELSEWHERE, 'Birth' ) ),
				PageSubjects::newEmpty(),
				$after
			)
		);
	}

}
