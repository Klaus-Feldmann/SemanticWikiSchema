<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application;

use MediaWiki\Title\Title;
use MediaWiki\Title\TitleValue;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\PageDependencyRecorder;
use ProfessionalWiki\SWS\Application\PageIdentifiersLookup;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Application\SubjectContentRepository;
use ProfessionalWiki\SWS\Application\SubjectResolver;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\NeoValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectContentRepository;
use ProfessionalWiki\SWS\Application\SubjectLookup;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\NullPageDependencyRecorder;
use ProfessionalWiki\SWS\Tests\TestDoubles\SelectivePageReadAuthorizer;
use ProfessionalWiki\SWS\Tests\TestDoubles\SpyPageDependencyRecorder;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\SWS\Application\SubjectResolver
 */
class SubjectResolverTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string SOURCED_SUBJECT_ID = TestSubjectIds::OTHER_SOURCE_KEY . ':Q42';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';
	private const int TARGET_PAGE_ID = 42;
	private const string TARGET_PAGE_NAME = 'Marie Curie';

	private function createSubject( string $id = self::SUBJECT_ID, string $label = 'Test Subject' ): Subject {
		return new Subject(
			id: new SubjectId( $id ),
			label: new SubjectLabel( $label ),
			schema: SchemaReference::local( new SchemaName( 'TestSchema' ) ),
			statements: new StatementList(),
		);
	}

	private function repositoryWithMainSubject( Subject $subject ): InMemorySubjectContentRepository {
		return new InMemorySubjectContentRepository( new PageSubjects( $subject, new SubjectMap() ) );
	}

	private function newResolver(
		SubjectContentRepository $contentRepository,
		?PageIdentifiersLookup $pageIdentifiersLookup = null,
		?PageReadAuthorizer $readAuthorizer = null,
		?SubjectLookup $subjectLookup = null,
		?PageDependencyRecorder $pageDependencyRecorder = null,
		?SchemaLookup $schemaLookup = null
	): SubjectResolver {
		return new SubjectResolver(
			$contentRepository,
			$subjectLookup ?? new InMemorySubjectLookup(),
			$pageIdentifiersLookup ?? new InMemoryPageIdentifiersLookup(),
			$readAuthorizer ?? new StubPageReadAuthorizer( true ),
			TestSubjectIds::newParser(),
			$pageDependencyRecorder ?? new NullPageDependencyRecorder(),
			TestSources::newSchemaResolver( $schemaLookup )
		);
	}

	/**
	 * The production lookups find a Subject through the page hosting it, so a Subject that exists
	 * is one the page index maps to a page.
	 */
	private function hostedOnTargetPage( string $subjectId ): InMemoryPageIdentifiersLookup {
		return new InMemoryPageIdentifiersLookup( [
			[ new SubjectId( $subjectId ), $this->newTargetPageIdentifiers() ],
		] );
	}

	private function repositoryHostingOnTargetPage( PageSubjects $pageSubjects ): InMemorySubjectContentRepository {
		$repository = new InMemorySubjectContentRepository();
		$repository->setContentForPageId( self::TARGET_PAGE_ID, $pageSubjects );

		return $repository;
	}

	public function testResolveByIdReturnsSubject(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( null, new SubjectMap( $subject ) ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID )
		);

		$this->assertSame( $subject, $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenTheHostingPageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID ),
			new SelectivePageReadAuthorizer( [ self::TARGET_PAGE_ID ] )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenNoPageHostsTheSubject(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullWhenTheHostingPageNoLongerHoldsTheSubject(): void {
		$resolver = $this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createTargetSubject( 'Someone else' ), new SubjectMap() ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID )
		);

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	/**
	 * A Subject of another Source has no page of this wiki to read it off, so it is served on its
	 * Source's vouch rather than through the page gate (ADR 23).
	 */
	public function testResolveByIdServesASourcedSubjectWithoutAHostingPage(): void {
		$sourced = $this->createSubject( self::SOURCED_SUBJECT_ID );

		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			null,
			new SelectivePageReadAuthorizer( [] ),
			new InMemorySubjectLookup( $sourced )
		);

		$this->assertSame( $sourced, $resolver->resolveById( self::SOURCED_SUBJECT_ID ) );
	}

	public function testResolveByIdReturnsNullForInvalidId(): void {
		$resolver = $this->newResolver( new InMemorySubjectContentRepository() );

		$this->assertNull( $resolver->resolveById( 'invalid' ) );
	}

	public function testResolveByIdReturnsNullWhenThePageLookupThrows(): void {
		$pageIdentifiersLookup = $this->createStub( PageIdentifiersLookup::class );
		$pageIdentifiersLookup->method( 'getPageIdOfSubject' )
			->willThrowException( new RuntimeException( 'db error' ) );

		$resolver = $this->newResolver( new InMemorySubjectContentRepository(), $pageIdentifiersLookup );

		$this->assertNull( $resolver->resolveById( self::SUBJECT_ID ) );
	}

	public function testResolveMainByTitleReturnsNullWhenThePageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $this->createSubject() ),
			readAuthorizer: new StubPageReadAuthorizer( false )
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenThePageIsNotReadable(): void {
		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $this->createSubject() ),
			readAuthorizer: new StubPageReadAuthorizer( false )
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsMainSubject(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
		);

		$this->assertSame( $subject, $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testResolveMainByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertNull( $resolver->resolveMainByTitle( $this->createStub( Title::class ) ) );
	}

	public function testGetPageSubjectsByTitleReturnsPageSubjects(): void {
		$subject = $this->createSubject();

		$resolver = $this->newResolver(
			$this->repositoryWithMainSubject( $subject ),
		);

		$pageSubjects = $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) );

		$this->assertNotNull( $pageSubjects );
		$this->assertSame( $subject, $pageSubjects->getMainSubject() );
	}

	public function testGetPageSubjectsByTitleReturnsNullWhenNoContent(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertNull( $resolver->getPageSubjectsByTitle( $this->createStub( Title::class ) ) );
	}

	public function testPageReadByTitleIsADependencyEvenWhenItMayNotBeRead(): void {
		$dependencies = new SpyPageDependencyRecorder();

		$this->newResolver(
			$this->repositoryWithMainSubject( $this->createSubject() ),
			readAuthorizer: new StubPageReadAuthorizer( false ),
			pageDependencyRecorder: $dependencies
		)->getPageSubjectsByTitle( Title::makeTitle( NS_MAIN, 'Restricted page' ) );

		$this->assertEquals( [ new TitleValue( NS_MAIN, 'Restricted_page' ) ], $dependencies->recordedPages );
	}

	/**
	 * The hosting page is outside the main namespace, so the name the index gives is prefixed.
	 */
	public function testPageHostingASubjectReadByIdIsADependency(): void {
		$dependencies = new SpyPageDependencyRecorder();

		$this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) ),
			new InMemoryPageIdentifiersLookup( [
				[
					new SubjectId( self::SUBJECT_ID ),
					new PageIdentifiers( new PageId( self::TARGET_PAGE_ID ), 'Help:Marie Curie', NS_HELP ),
				],
			] ),
			pageDependencyRecorder: $dependencies
		)->resolveById( self::SUBJECT_ID );

		$this->assertEquals( [ new TitleValue( NS_HELP, 'Marie_Curie' ) ], $dependencies->recordedPages );
	}

	public function testPageHostingASubjectIsNoDependencyWhenItMayNotBeRead(): void {
		$dependencies = new SpyPageDependencyRecorder();

		$this->newResolver(
			$this->repositoryHostingOnTargetPage( new PageSubjects( $this->createSubject(), new SubjectMap() ) ),
			$this->hostedOnTargetPage( self::SUBJECT_ID ),
			new SelectivePageReadAuthorizer( [ self::TARGET_PAGE_ID ] ),
			pageDependencyRecorder: $dependencies
		)->resolveById( self::SUBJECT_ID );

		$this->assertSame( [], $dependencies->recordedPages );
	}

	private function newResolverWithTargetOnPage(
		PageSubjects $hostingPageSubjects,
		?PageReadAuthorizer $readAuthorizer = null
	): SubjectResolver {
		return $this->newResolver(
			$this->repositoryHostingOnTargetPage( $hostingPageSubjects ),
			$this->hostedOnTargetPage( self::TARGET_SUBJECT_ID ),
			$readAuthorizer
		);
	}

	private function newTargetPageIdentifiers(): PageIdentifiers {
		return new PageIdentifiers(
			id: new PageId( self::TARGET_PAGE_ID ),
			title: self::TARGET_PAGE_NAME,
			namespaceId: 0,
		);
	}

	private function createTargetSubject( ?string $label ): Subject {
		return new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: $label === null ? null : new SubjectLabel( $label ),
			schema: SchemaReference::local( new SchemaName( 'Person' ) ),
			statements: new StatementList(),
		);
	}

	private function newRelationToTarget(): Relation {
		return new Relation(
			id: new RelationId( 'r1test5cccccccc' ),
			targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
		);
	}

	public function testResolveRelationLabelReturnsLabelWhenTargetExists(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( null, new SubjectMap( $this->createTargetSubject( 'Jane Doe' ) ) )
		);

		$this->assertSame( 'Jane Doe', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelPrefersAStoredLabelOverThePageNameOfAMainSubjectTarget(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( 'Jane Doe' ), new SubjectMap() )
		);

		$this->assertSame( 'Jane Doe', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToThePageNameWhenTheTargetIsALabellessMainSubject(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( null ), new SubjectMap() )
		);

		$this->assertSame( self::TARGET_PAGE_NAME, $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToTheSchemaNameWhenTheTargetIsALabellessOtherSubject(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects(
				$this->createSubject( self::SUBJECT_ID, 'The Page Topic' ),
				new SubjectMap( $this->createTargetSubject( null ) )
			)
		);

		$this->assertSame( 'Person', $resolver->resolveRelationLabel( $this->newRelationToTarget() ) );
	}

	public function testResolveRelationLabelFallsBackToIdWhenTheHostingPageIsNotReadable(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createTargetSubject( 'Jane Doe' ), new SubjectMap() ),
			new SelectivePageReadAuthorizer( [ self::TARGET_PAGE_ID ] )
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenTargetNotFound(): void {
		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenTheHostingPageNoLongerHoldsTheTarget(): void {
		$resolver = $this->newResolverWithTargetOnPage(
			new PageSubjects( $this->createSubject( self::SUBJECT_ID, 'The Page Topic' ), new SubjectMap() )
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	public function testResolveRelationLabelFallsBackToIdWhenLookupThrows(): void {
		$pageIdentifiersLookup = $this->createStub( PageIdentifiersLookup::class );
		$pageIdentifiersLookup->method( 'getPageIdOfSubject' )
			->willThrowException( new RuntimeException( 'db error' ) );

		$resolver = $this->newResolver(
			new InMemorySubjectContentRepository(),
			$pageIdentifiersLookup
		);

		$this->assertSame(
			self::TARGET_SUBJECT_ID,
			$resolver->resolveRelationLabel( $this->newRelationToTarget() )
		);
	}

	private function selectStatement( string ...$optionIds ): Statement {
		return TestStatement::build( 'Status', new StringValue( ...$optionIds ), SelectType::NAME );
	}

	/**
	 * The Schema of {@see createSubject()}, giving it a Status property.
	 */
	private function schemaGivingStatus( PropertyDefinition $status ): InMemorySchemaLookup {
		return new InMemorySchemaLookup( TestSchema::build(
			name: 'TestSchema',
			properties: new PropertyDefinitions( [ 'Status' => $status ] )
		) );
	}

	private function statusWithOptions(): PropertyDefinition {
		return TestProperty::buildSelect( options: [
			new SelectOption( 'o1', 'Draft' ),
			new SelectOption( 'o2', 'In review' ),
			new SelectOption( 'o3', 'Published' ),
			new SelectOption( 'o4', 'Archived' ),
		] );
	}

	private function displayValueOf(
		Statement $statement,
		SchemaLookup $schemaLookup,
		?PageDependencyRecorder $pageDependencyRecorder = null
	): NeoValue {
		return $this->newResolver(
			new InMemorySubjectContentRepository(),
			pageDependencyRecorder: $pageDependencyRecorder,
			schemaLookup: $schemaLookup
		)->resolveDisplayValue( $this->createSubject(), $statement );
	}

	public function testSelectValueShowsTheLabelsOfItsOptions(): void {
		$this->assertEquals(
			new StringValue( 'In review', 'Published' ),
			$this->displayValueOf( $this->selectStatement( 'o2', 'o3' ), $this->schemaGivingStatus( $this->statusWithOptions() ) )
		);
	}

	public function testOptionTheSchemaNoLongerHasShowsItsId(): void {
		$this->assertEquals(
			new StringValue( 'In review', 'o9' ),
			$this->displayValueOf( $this->selectStatement( 'o2', 'o9' ), $this->schemaGivingStatus( $this->statusWithOptions() ) )
		);
	}

	public function testSelectValueShowsItsIdsWhenTheSchemaCannotBeRead(): void {
		$this->assertEquals(
			new StringValue( 'o2' ),
			$this->displayValueOf( $this->selectStatement( 'o2' ), new InMemorySchemaLookup() )
		);
	}

	public function testSelectValueShowsItsIdsWhenReadingTheSchemaFails(): void {
		$schemaLookup = $this->createStub( SchemaLookup::class );
		$schemaLookup->method( 'getSchema' )->willThrowException( new RuntimeException( 'Source unreachable' ) );

		$this->assertEquals(
			new StringValue( 'o2' ),
			$this->displayValueOf( $this->selectStatement( 'o2' ), $schemaLookup )
		);
	}

	public function testSelectValueShowsItsIdsWhenTheSchemaNoLongerHasTheProperty(): void {
		$schemaWithoutStatus = new InMemorySchemaLookup( TestSchema::build(
			name: 'TestSchema',
			properties: new PropertyDefinitions( [ 'Phase' => $this->statusWithOptions() ] )
		) );

		$this->assertEquals(
			new StringValue( 'o2' ),
			$this->displayValueOf( $this->selectStatement( 'o2' ), $schemaWithoutStatus )
		);
	}

	public function testSelectValueShowsItsIdsWhenTheSchemaNowGivesThePropertyAnotherType(): void {
		$this->assertEquals(
			new StringValue( 'o2' ),
			$this->displayValueOf( $this->selectStatement( 'o2' ), $this->schemaGivingStatus( TestProperty::buildText() ) )
		);
	}

	/**
	 * The Statement keeps the type it was written with until it is saved again.
	 */
	public function testTextValueShowsAsStoredWhenTheSchemaNowMakesThePropertyASelect(): void {
		$this->assertEquals(
			new StringValue( 'o2' ),
			$this->displayValueOf( TestStatement::build( 'Status', 'o2' ), $this->schemaGivingStatus( $this->statusWithOptions() ) )
		);
	}

	/**
	 * Creating or restoring the Schema then refreshes the ids shown in its absence.
	 */
	public function testShowingASelectValueDependsOnItsSchemaEvenWhenItCannotBeRead(): void {
		$dependencies = new SpyPageDependencyRecorder();

		$this->displayValueOf( $this->selectStatement( 'o2' ), new InMemorySchemaLookup(), $dependencies );

		$this->assertEquals( [ SchemaReference::local( new SchemaName( 'TestSchema' ) ) ], $dependencies->recordedSchemas );
	}

}
