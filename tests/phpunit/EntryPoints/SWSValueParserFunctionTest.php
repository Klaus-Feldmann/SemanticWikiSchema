<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\PageIdentifiersLookup;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\Application\SubjectContentRepository;
use ProfessionalWiki\SWS\Application\SubjectResolver;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\SWS\Domain\Relation\Relation;
use ProfessionalWiki\SWS\Domain\Relation\RelationId;
use ProfessionalWiki\SWS\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\BooleanValue;
use ProfessionalWiki\SWS\Domain\Value\MonolingualText;
use ProfessionalWiki\SWS\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\RelationValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\SWS\EntryPoints\SWSValueParserFunction;
use ProfessionalWiki\SWS\Tests\Data\TestProperty;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSources;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectContentRepository;
use ProfessionalWiki\SWS\Tests\TestDoubles\NullPageDependencyRecorder;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSValueParserFunction
 */
class SWSValueParserFunctionTest extends TestCase {

	private const string SUBJECT_ID = 's1test5aaaaaaaa';
	private const string TARGET_SUBJECT_ID = 's1test5bbbbbbbb';

	private function createMockParser(): Parser {
		$title = $this->createStub( Title::class );

		$parser = $this->createStub( Parser::class );
		$parser->method( 'getTitle' )->willReturn( $title );
		$parser->method( 'incrementExpensiveFunctionCount' )->willReturn( true );

		return $parser;
	}

	private function createSubject( Statement ...$statements ): Subject {
		return new Subject(
			id: new SubjectId( self::SUBJECT_ID ),
			label: new SubjectLabel( 'Test Subject' ),
			schema: SchemaReference::local( new SchemaName( 'TestSchema' ) ),
			statements: new StatementList( $statements ),
		);
	}

	private function repositoryWithSubject( Subject $subject ): InMemorySubjectContentRepository {
		return new InMemorySubjectContentRepository( new PageSubjects( $subject, new SubjectMap() ) );
	}

	private function createPF(
		SubjectContentRepository $repo,
		?PageIdentifiersLookup $pageIdentifiersLookup = null,
		?PageReadAuthorizer $readAuthorizer = null
	): SWSValueParserFunction {
		return new SWSValueParserFunction(
			new SubjectResolver(
				$repo,
				new InMemorySubjectLookup(),
				$pageIdentifiersLookup ?? new InMemoryPageIdentifiersLookup(),
				$readAuthorizer ?? new StubPageReadAuthorizer( true ),
				TestSubjectIds::newParser(),
				new NullPageDependencyRecorder(),
				TestSources::newSchemaResolver( $this->newSchemaLookup() )
			)
		);
	}

	/**
	 * The Schema of {@see createSubject()}, whose Status property is a select.
	 */
	private function newSchemaLookup(): InMemorySchemaLookup {
		return new InMemorySchemaLookup( TestSchema::build(
			name: 'TestSchema',
			properties: new PropertyDefinitions( [
				'Status' => TestProperty::buildSelect( options: [
					new SelectOption( 'o1', 'Draft' ),
					new SelectOption( 'o2', 'In review' ),
					new SelectOption( 'o3', 'Published' ),
					new SelectOption( 'o4', 'Archived' ),
				] ),
			] )
		) );
	}

	/**
	 * Relation targets are resolved through the page hosting them, so a target that exists is one
	 * placed on a page of its own, as its Main Subject.
	 */
	private function placeOnOwnPages(
		InMemorySubjectContentRepository $repo,
		Subject ...$targets
	): InMemoryPageIdentifiersLookup {
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageId = 1000;

		foreach ( $targets as $target ) {
			$pageId++;

			$repo->setContentForPageId( $pageId, new PageSubjects( $target, new SubjectMap() ) );
			$pageIdentifiersLookup->addIdentifiers(
				$target->getId(),
				new PageIdentifiers( new PageId( $pageId ), 'Page ' . $pageId, 0 )
			);
		}

		return $pageIdentifiersLookup;
	}

	private function assertEscapedText( string $expectedHtml, string|array $result ): void {
		$this->assertIsArray( $result );
		$this->assertSame( wfEscapeWikiText( $expectedHtml ), $result[0] );
		$this->assertTrue( $result['noparse'] );
		$this->assertFalse( $result['isHTML'] );
	}

	// --- String values ---

	public function testReturnsStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'City' );

		$this->assertEscapedText( 'Berlin', $result );
	}

	public function testReturnsEmptyWhenTheSubjectsPageIsNotReadable(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF(
			$this->repositoryWithSubject( $subject ),
			readAuthorizer: new StubPageReadAuthorizer( false )
		)->handle( $this->createMockParser(), 'City' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringForValueOfUnregisteredType(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Swatch' ), 'color', new UnregisteredTypeValue( 'color', [ '#ff5733' ] ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Swatch' );

		$this->assertSame( '', $result );
	}

	public function testReturnsMultiValueStringWithDefaultSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags' );

		$this->assertEscapedText( 'alpha, beta, gamma', $result );
	}

	public function testReturnsMultiValueStringWithCustomSeparator(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', 'separator=;' );

		$this->assertEscapedText( 'alpha;beta', $result );
	}

	public function testTrimsWhitespaceAroundSeparatorParam(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', ' separator = ; ' );

		$this->assertEscapedText( 'alpha;beta', $result );
	}

	public function testEmptySeparatorConcatenatesValues(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'a', 'b', 'c' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Tags', 'separator=' );

		$this->assertEscapedText( 'abc', $result );
	}

	// --- Select values ---

	public function testReturnsTheLabelsOfSelectOptions(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Status' ), SelectType::NAME, new StringValue( 'o2', 'o3' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Status' );

		$this->assertEscapedText( 'In review, Published', $result );
	}

	// --- Monolingual text values ---

	public function testJoinsTheTextsOfAMonolingualTextValue(): void {
		$result = $this->createPF( $this->repositoryWithSubject( $this->subjectWithOriginalTitle() ) )
			->handle( $this->createMockParser(), 'Original title' );

		$this->assertEscapedText( 'Zinema, Cine', $result );
	}

	public function testJoinsTheTextsOfAMonolingualTextValueWithTheGivenSeparator(): void {
		$result = $this->createPF( $this->repositoryWithSubject( $this->subjectWithOriginalTitle() ) )
			->handle( $this->createMockParser(), 'Original title', 'separator=;' );

		$this->assertEscapedText( 'Zinema;Cine', $result );
	}

	private function subjectWithOriginalTitle(): Subject {
		return $this->createSubject( new Statement(
			new PropertyName( 'Original title' ),
			'monolingualText',
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Cine', 'es' ),
			)
		) );
	}

	// --- Number values ---

	public function testReturnsNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Age' );

		$this->assertEscapedText( '42', $result );
	}

	public function testReturnsFloatNumberValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Price' ), 'number', new NumberValue( 19.99 ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Price' );

		$this->assertEscapedText( '19.99', $result );
	}

	// --- Boolean values ---

	public function testReturnsTrueBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( true ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Active' );

		$this->assertEscapedText( 'true', $result );
	}

	public function testReturnsFalseBooleanValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Active' ), 'boolean', new BooleanValue( false ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Active' );

		$this->assertEscapedText( 'false', $result );
	}

	// --- Relation values ---

	public function testReturnsRelationLabelWhenTargetExists(): void {
		$targetSubject = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Sarah Naumann' ),
			schema: SchemaReference::local( new SchemaName( 'Person' ) ),
			statements: new StatementList(),
		);

		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Process owner' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5cccccccc' ),
						targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
					)
				)
			)
		);

		$repo = $this->repositoryWithSubject( $subject );

		$result = $this->createPF( $repo, $this->placeOnOwnPages( $repo, $targetSubject ) )
			->handle( $this->createMockParser(), 'Process owner' );

		$this->assertEscapedText( 'Sarah Naumann', $result );
	}

	public function testReturnsRelationIdWhenTargetNotFound(): void {
		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Process owner' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5cccccccc' ),
						targetId: new SubjectId( self::TARGET_SUBJECT_ID ),
					)
				)
			)
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Process owner' );

		$this->assertEscapedText( self::TARGET_SUBJECT_ID, $result );
	}

	public function testMultipleRelationLabels(): void {
		$target1 = new Subject(
			id: new SubjectId( 's1test5bbbbbbbb' ),
			label: new SubjectLabel( 'Alice' ),
			schema: SchemaReference::local( new SchemaName( 'Person' ) ),
			statements: new StatementList(),
		);
		$target2 = new Subject(
			id: new SubjectId( 's1test5cccccccc' ),
			label: new SubjectLabel( 'Bob' ),
			schema: SchemaReference::local( new SchemaName( 'Person' ) ),
			statements: new StatementList(),
		);

		$subject = $this->createSubject(
			new Statement(
				new PropertyName( 'Members' ),
				'relation',
				new RelationValue(
					new Relation(
						id: new RelationId( 'r1test5dddddddd' ),
						targetId: new SubjectId( 's1test5bbbbbbbb' ),
					),
					new Relation(
						id: new RelationId( 'r1test5eeeeeeee' ),
						targetId: new SubjectId( 's1test5cccccccc' ),
					),
				)
			)
		);

		$repo = $this->repositoryWithSubject( $subject );

		$result = $this->createPF( $repo, $this->placeOnOwnPages( $repo, $target1, $target2 ) )
			->handle( $this->createMockParser(), 'Members' );

		$this->assertEscapedText( 'Alice, Bob', $result );
	}

	public function testReturnsEmptyStringForEmptyRelationValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Members' ), 'relation', new RelationValue() )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Members' );

		$this->assertSame( '', $result );
	}

	// --- Empty / missing cases ---

	public function testReturnsEmptyStringForMissingProperty(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Nonexistent' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWhenNoSubjectOnPage(): void {
		$result = $this->createPF( new InMemorySubjectContentRepository() )
			->handle( $this->createMockParser(), 'City' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringForEmptyPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), '' );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringWithNoArguments(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser() );

		$this->assertSame( '', $result );
	}

	public function testReturnsEmptyStringForEmptyStringValue(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue() )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'City' );

		$this->assertSame( '', $result );
	}

	// --- Parameter handling ---

	public function testTrimsPropertyName(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), '  City  ' );

		$this->assertEscapedText( 'Berlin', $result );
	}

	public function testEntityThenField(): void {
		$targetSubject = new Subject(
			id: new SubjectId( 'E2' ),
			identity: new \ProfessionalWiki\SWS\Domain\Identity\LocalizedIdentity( 'en', [ 'en' => 'Other Subject' ] ),
			label: new SubjectLabel( 'Other Subject' ),
			schema: SchemaReference::local( new SchemaName( 'TestSchema' ) ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Munich' ) ),
			] ),
		);

		$repo = new InMemorySubjectContentRepository();
		$pf = $this->createPF( $repo, $this->placeOnOwnPages( $repo, $targetSubject ) );

		$result = $pf->handle( $this->createMockParser(), 'E2', 'City' );

		$this->assertEscapedText( 'Munich', $result );
	}

	public function testInvalidSubjectIdReturnsEmptyString(): void {
		$result = $this->createPF( new InMemorySubjectContentRepository() )
			->handle( $this->createMockParser(), 'City', 'subject=invalid' );

		$this->assertSame( '', $result );
	}

	public function testPageParam(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Hamburg' ) )
		);

		$repo = new InMemorySubjectContentRepository();
		$repo->setContentForPage( 'Other_Page', new PageSubjects( $subject, new SubjectMap() ) );

		$result = $this->createPF( $repo )
			->handle( $this->createMockParser(), 'City', 'page=Other Page' );

		$this->assertEscapedText( 'Hamburg', $result );
	}

	public function testEntityAndPageAreRejectedAsAmbiguous(): void {
		$subjectViaId = new Subject(
			id: new SubjectId( self::TARGET_SUBJECT_ID ),
			label: new SubjectLabel( 'Via ID' ),
			schema: SchemaReference::local( new SchemaName( 'TestSchema' ) ),
			statements: new StatementList( [
				new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'FromSubject' ) ),
			] ),
		);

		$subjectViaPage = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'FromPage' ) )
		);

		$repo = $this->repositoryWithSubject( $subjectViaPage );
		$result = $this->createPF( $repo, $this->placeOnOwnPages( $repo, $subjectViaId ) )->handle(
			$this->createMockParser(),
			'E2',
			'City',
			'page=Some Page'
		);

		$this->assertSame( '', $result );
	}

	public function testReturnsCorrectPropertyFromMultipleStatements(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
			new Statement( new PropertyName( 'Country' ), 'text', new StringValue( 'Germany' ) ),
			new Statement( new PropertyName( 'Population' ), 'number', new NumberValue( 3_700_000 ) ),
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Country' );

		$this->assertEscapedText( 'Germany', $result );
	}

	// --- HTML escaping ---

	public function testOutputIsNotParsedAsWikitext(): void {
		$subject = $this->createSubject(
			new Statement( new PropertyName( 'Name' ), 'text', new StringValue( '<b>bold</b> & "quoted"' ) )
		);

		$result = $this->createPF( $this->repositoryWithSubject( $subject ) )
			->handle( $this->createMockParser(), 'Name' );

		$this->assertIsArray( $result );
		$this->assertSame( wfEscapeWikiText( '<b>bold</b> & "quoted"' ), $result[0] );
		$this->assertTrue( $result['noparse'] );
		$this->assertFalse( $result['isHTML'] );
	}

}
