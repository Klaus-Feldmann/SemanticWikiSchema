<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MediaWiki\Output\OutputPage;
use MediaWiki\Parser\Parser;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphBackendNotConfiguredException;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\EntryPoints\REST\GetReferencingSubjectsApi;
use ProfessionalWiki\SWS\EntryPoints\REST\GetSubjectLabelsApi;
use ProfessionalWiki\SWS\EntryPoints\Scribunto\SubjectDataLookup;
use ProfessionalWiki\SWS\Infrastructure\AuthorityBasedRawQueryAuthorizer;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\TestRelation;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use TestLogger;

/**
 * A wiki with no graph backend configured is a supported mode: Subjects are stored, found, read and
 * rendered without one, and only the query surfaces a backend brings are unavailable (#1040).
 *
 * @covers \ProfessionalWiki\SWS\SWSExtension
 * @group Database
 */
class NoGraphBackendTest extends SWSIntegrationTestCase {
	use HandlerTestTrait;

	private const SUBJECT_ID = 's1zz1111111azz1';
	private const REFERRING_SUBJECT_ID = 's1zz1111111azz2';
	private const PROPERTY = 'Motto';
	private const VALUE = 'Beyond the graph';

	public function testGetNeo4jPluginIsNullWithoutBackend(): void {
		$plugin = $this->runWithoutGraphBackend(
			static fn() => SWSExtension::getInstance()->getNeo4jPlugin()
		);

		$this->assertNull( $plugin );
	}

	public function testGetNeo4jPluginIsPresentWhenConfigured(): void {
		$this->assertNotNull( SWSExtension::getInstance()->getNeo4jPlugin() );
	}

	public function testEditSucceedsWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
				->newFromTitle( Title::newFromText( 'NoBackendEditPage' ) );

			$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
			$updater->setContent( 'main', new WikitextContent( 'hello' ) );
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'no-backend edit' ) );

			$this->assertTrue( $updater->wasRevisionCreated() );
		} );
	}

	public function testSubjectIsStoredAndFoundByIdWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendSubjectPage' );

			$subject = SWSExtension::getInstance()->getSubjectRepository()
				->getSubject( new SubjectId( self::SUBJECT_ID ) );

			$this->assertNotNull( $subject );
			$this->assertSame( self::SUBJECT_ID, $subject->id->text );
		} );
	}

	public function testSubjectIsEditedThroughTheRepositoryWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendEditedSubjectPage' );
			$repository = SWSExtension::getInstance()->getSubjectRepository();

			$repository->updateSubject(
				$this->mottoSubject( 'Rewritten' ),
				'no-backend subject edit'
			);

			$this->assertSame( 'Rewritten', $this->mottoOfIndexedSubject() );
		} );
	}

	public function testSubjectIsDeletedThroughTheRepositoryWithoutBackend(): void {
		$this->runWithoutGraphBackend( function (): void {
			$this->createPageWithMottoSubject( 'NoBackendDeletedSubjectPage' );
			$repository = SWSExtension::getInstance()->getSubjectRepository();

			$repository->deleteSubject( new SubjectId( self::SUBJECT_ID ), 'no-backend subject delete' );

			$this->assertNull( $repository->getSubject( new SubjectId( self::SUBJECT_ID ) ) );
		} );
	}

	public function testValueParserFunctionReadsAValueWithoutBackend(): void {
		$html = $this->runWithoutGraphBackend( function (): string {
			$this->createPageWithMottoSubject( 'NoBackendValuePage' );

			return $this->parseWikitextOn(
				'NoBackendValuePage',
				'{{#sws_value: ' . self::PROPERTY . ' | subject=' . self::SUBJECT_ID . ' }}'
			);
		} );

		$this->assertStringContainsString( self::VALUE, $html );
	}

	public function testCypherRawExplainsItCannotRunWithoutNeo4j(): void {
		$html = $this->runWithoutGraphBackend(
			fn (): string => $this->parseWikitextOn(
				'NoBackendCypherPage',
				'{{#cypher_raw: MATCH (n) RETURN n }}',
				$this->parserOptionsShowingMessageKeys()
			)
		);

		$this->assertStringContainsString( 'class="error"', $html );
		$this->assertStringContainsString( '(sws-cypher-raw-error-not-configured)', $html );
	}

	/**
	 * The engine behind the mw.sws.* getters, reached by Subject id, which is the path that needed
	 * the reverse index.
	 */
	public function testLuaGetterReadsAValueBySubjectIdWithoutBackend(): void {
		$value = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendLuaPage' );
			$resolver = SWSExtension::getInstance()->newSubjectResolver( $this->sysopParserOn( 'NoBackendLuaPage' ) );
			return ( new SubjectDataLookup( $resolver ) )->getValue(
				Title::newFromText( 'NoBackendLuaPage' ),
				self::PROPERTY,
				[ 'subject' => self::SUBJECT_ID ]
			);
		} );

		$this->assertSame( [ self::VALUE ], $value );
	}

	/**
	 * A parser part way through parsing the page for a sysop, as the Lua library holds one.
	 */
	private function sysopParserOn( string $pageName ): Parser {
		$parser = $this->getServiceContainer()->getParserFactory()->create();
		$parser->startExternalParse(
			Title::newFromText( $pageName ),
			ParserOptions::newFromUser( $this->getTestSysop()->getUser() ),
			Parser::OT_HTML
		);

		return $parser;
	}

	public function testContentPageRenderInjectsTheAppWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendAppPage' );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			SWSHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertStringContainsString( 'ext-sws-app', $out->getHTML() );
	}

	public function testContentPageRenderInjectsTheAppWhenConfigured(): void {
		$out = $this->newContentPageOutput( 'ConfiguredBackendViewPage' );

		SWSHooks::onBeforePageDisplay( $out, $out->getSkin() );

		$this->assertStringContainsString( 'ext-sws-app', $out->getHTML() );
	}

	public function testContentPageRenderIsSilentWithoutBackend(): void {
		$out = $this->newContentPageOutput( 'NoBackendQuietPage' );
		$logger = new TestLogger( true );
		$this->setLogger( 'SWS', $logger );

		$this->runWithoutGraphBackend( static function () use ( $out ): void {
			SWSHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertSame( [], $logger->getBuffer() );
	}

	/**
	 * Half a Neo4j configuration is not a supported mode but an unfinished one, so it is still reported.
	 */
	public function testHalfConfiguredNeo4jIsReported(): void {
		$out = $this->newContentPageOutput( 'HalfConfiguredPage' );
		$logger = new TestLogger( true );
		$this->setLogger( 'SWS', $logger );

		$this->runWithoutGraphBackend( function () use ( $out ): void {
			$this->overrideConfigValue( 'SWSNeo4jInternalReadUrl', 'bolt://neo:7687' );
			SWSExtension::resetInstance();

			SWSHooks::onBeforePageDisplay( $out, $out->getSkin() );
		} );

		$this->assertStringContainsString( 'only one of the Neo4j read/write Bolt URLs', self::loggedText( $logger ) );
	}

	public function testRelationTargetsAreSuggestedWithoutBackend(): void {
		$suggestions = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendSuggestedPage' );

			return $this->getJson(
				new GetSubjectLabelsApi(),
				[ 'queryParams' => [ 'search' => 'Test sub' ] ]
			);
		} );

		$this->assertSame( [ self::SUBJECT_ID ], array_column( $suggestions, 'id' ) );
	}

	public function testReferencingSubjectsAreFoundWithoutBackend(): void {
		$referencing = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendReferencedPage' );
			$this->createPageWithSubjects( 'NoBackendReferringPage', TestSubject::build(
				id: self::REFERRING_SUBJECT_ID,
				statements: new StatementList( [
					TestStatement::buildRelation( 'Inspired by', [ TestRelation::build( targetId: self::SUBJECT_ID ) ] ),
				] )
			) );

			return $this->getJson(
				new GetReferencingSubjectsApi(),
				[ 'pathParams' => [ 'subjectId' => self::SUBJECT_ID ] ]
			);
		} );

		$this->assertSame(
			[ self::REFERRING_SUBJECT_ID ],
			array_map( static fn ( array $row ): string => $row['subject']['id'], $referencing['referencingSubjects'] )
		);
	}

	public function testSubjectsAreListedWithoutBackend(): void {
		$listing = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendListedPage' );

			return $this->getJson( SWSExtension::newGetSubjectSummariesApi(), [] );
		} );

		$this->assertSame( [ self::SUBJECT_ID ], array_column( $listing['subjects'], 'id' ) );
	}

	public function testSubjectsAreCountedWithoutBackend(): void {
		$counts = $this->runWithoutGraphBackend( function (): array {
			$this->createPageWithMottoSubject( 'NoBackendCountedPage' );

			return $this->getJson(
				SWSExtension::newGetSubjectCountsApi(),
				[],
				$this->mockAnonAuthorityWithPermissions( [ AuthorityBasedRawQueryAuthorizer::RIGHT ] )
			);
		} );

		$this->assertSame( [ TestSubject::DEFAULT_SCHEMA_ID => 1 ], $counts['counts'] );
	}

	/**
	 * @param array<string, mixed> $request
	 * @return array<mixed>
	 */
	private function getJson( Handler $handler, array $request, ?Authority $authority = null ): array {
		return json_decode(
			$this->executeHandler( $handler, new RequestData( [ 'method' => 'GET' ] + $request ), authority: $authority )
				->getBody()->getContents(),
			true
		);
	}

	public function testLuaQueryOfferedWhenConfigured(): void {
		$names = SWSExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? [];

		$this->assertContains( 'query', $names );
	}

	public function testLuaQueryNotOfferedWithoutBackend(): void {
		$names = $this->runWithoutGraphBackend(
			static fn() => SWSExtension::getInstance()->getNeo4jPlugin()?->getLuaLibraryFunctionNames() ?? []
		);

		$this->assertSame( [], $names );
	}

	public function testReadOnlyClientThrowsGraphBackendNotConfiguredExceptionWithoutBackend(): void {
		$this->expectException( GraphBackendNotConfiguredException::class );

		$this->runWithoutGraphBackend(
			static fn() => SWSExtension::getInstance()->getReadOnlyNeo4jClient()
		);
	}

	private function createPageWithMottoSubject( string $pageName ): void {
		$this->assertNotNull( $this->createPageWithSubjects( $pageName, $this->mottoSubject( self::VALUE ) ) );
	}

	private function mottoSubject( string $motto ): Subject {
		return TestSubject::build(
			id: self::SUBJECT_ID,
			statements: new StatementList( [
				new Statement( new PropertyName( self::PROPERTY ), 'text', new StringValue( $motto ) ),
			] )
		);
	}

	private function mottoOfIndexedSubject(): ?string {
		$subject = SWSExtension::getInstance()->getSubjectRepository()
			->getSubject( new SubjectId( self::SUBJECT_ID ) );

		$value = $subject?->getStatements()->getStatement( new PropertyName( self::PROPERTY ) )?->getValue();

		return $value instanceof StringValue ? $value->toScalars()[0] : null;
	}

	private function newContentPageOutput( string $pageName ): OutputPage {
		$page = $this->getExistingTestPage( $pageName );

		$context = new RequestContext();
		$context->setTitle( $page->getTitle() );
		$context->setUser( $this->getTestSysop()->getUser() );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $page->getLatest() );

		return $out;
	}

}
