<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\WikitextContent;
use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\ParserAuthority;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\ParseTimePermissionFixtures;
use WikiPage;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\ParserAuthority
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onParserOptionsRegister
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onParserFirstCallInit
 * @group Database
 */
class ParserAuthorityTest extends SWSIntegrationTestCase {

	use ParseTimePermissionFixtures;

	private const string PARSED_PAGE = 'ParserAuthorityTestPage';
	private const string SUBJECT_PAGE = 'ParserAuthorityTestSubjectPage';
	private const string VALUE_FUNCTION = '{{#sws_value: Motto | page=' . self::SUBJECT_PAGE . ' }}';

	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects(
			self::SUBJECT_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( 'Cached per class' ) ),
			] ) )
		);
	}

	private function sysopOptions(): ParserOptions {
		return ParserOptions::newFromUser( $this->getTestSysop()->getUser() );
	}

	/**
	 * Entries cached before SWS keyed by access class carry no such key, so a gated page must not
	 * land on the key those entries used, not even for an anonymous reader.
	 */
	public function testAGatedPageDoesNotReuseAnEntryCachedWithoutTheAccessClass(): void {
		$anonymousOptions = ParserOptions::newFromAnon();

		$this->assertNotSame(
			$anonymousOptions->optionsHash( [] ),
			$anonymousOptions->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] )
		);
	}

	/**
	 * Core compares every cache-varying option of the editor's options against the canonical ones on
	 * save, and defers the parser-cache update for every page when they differ.
	 */
	public function testEditorsOptionsStillMatchTheCanonicalOnesForPagesWithoutGatedReads(): void {
		$this->assertTrue( $this->sysopOptions()->matchesForCacheKey( ParserOptions::newFromAnon() ) );
	}

	public function testParseForALoggedInUserHasItsOwnCacheKey(): void {
		$this->assertNotSame(
			ParserOptions::newFromAnon()->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] ),
			$this->sysopOptions()->optionsHash( [ ParserAuthority::ACCESS_CLASS_OPTION ] )
		);
	}

	public function testReadingASubjectPutsTheAccessClassInThePagesCacheKey(): void {
		$output = $this->parserOutputOn( self::PARSED_PAGE, self::VALUE_FUNCTION, ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	/**
	 * The argument-less view reads its own page's Main Subject at parse time, and still only emits a marker.
	 */
	public function testRenderingAViewDoesNotFragmentTheCache(): void {
		$output = $this->parserOutputOn( self::SUBJECT_PAGE, '{{#view}}', ParserOptions::newFromAnon() );

		$this->assertNotContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testRunningACypherQueryPutsTheAccessClassInThePagesCacheKey(): void {
		$this->grantTheQueryRightToSysopsOnly();

		$output = $this->parserOutputOn( self::PARSED_PAGE, '{{#cypher_raw: RETURN 1 AS n }}', ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testRunningASparqlQueryPutsTheAccessClassInThePagesCacheKey(): void {
		$this->configureAnUnreachableSparqlStore();
		$this->grantTheQueryRightToSysopsOnly();

		$output = $this->parserOutputOn( self::PARSED_PAGE, '{{#sparql_raw: SELECT * WHERE { ?s ?p ?o } }}', ParserOptions::newFromAnon() );

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	public function testReadingASubjectFromLuaPutsTheAccessClassInThePagesCacheKey(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->editPage(
			Title::makeTitle( NS_MODULE, 'ParserAuthorityTest' ),
			new ScribuntoContent(
				"local nw = require( 'mw.sws' )\n" .
				"local p = {}\n" .
				"function p.motto( frame ) return nw.getValue( 'Motto', { page = frame.args[1] } ) or '' end\n" .
				"return p"
			)
		);

		$output = $this->parserOutputOn(
			self::PARSED_PAGE,
			'{{#invoke:ParserAuthorityTest|motto|' . self::SUBJECT_PAGE . '}}',
			ParserOptions::newFromAnon()
		);

		$this->assertContains( ParserAuthority::ACCESS_CLASS_OPTION, $output->getUsedOptions() );
	}

	/**
	 * Wikitext holding a gated read, plus a Subject slot of its own, so the render combines two slots
	 * as every SWS page does: the option the read records must survive that merge to reach the
	 * cache key.
	 */
	private function cachedGatedPage( ParserOptions $parserOptions ): WikiPage {
		$page = $this->getServiceContainer()->getWikiPageFactory()
			->newFromTitle( Title::makeTitle( NS_MAIN, 'ParserAuthorityTestCachedPage' ) );

		$updater = $page->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new WikitextContent( self::VALUE_FUNCTION ) );
		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData(
				new PageSubjects( TestSubject::build( id: 's1cached1111111' ), new SubjectMap() )
			)
		);
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Gated page' ) );

		$this->getServiceContainer()->getParserOutputAccess()->getParserOutput( $page, $parserOptions );

		return $page;
	}

	public function testCachedOutputOfOneClassIsNotServedToAnother(): void {
		$anonymousOptions = ParserOptions::newFromAnon();
		$page = $this->cachedGatedPage( $anonymousOptions );

		$parserCache = $this->getServiceContainer()->getParserCache();

		$this->assertNotFalse( $parserCache->get( $page, $anonymousOptions ), 'the anonymous parse is cached' );
		$this->assertFalse( $parserCache->get( $page, $this->sysopOptions() ), 'a sysop does not get it' );
	}

	public function testCachedOutputIsSharedWithinAnAccessClass(): void {
		$page = $this->cachedGatedPage( ParserOptions::newFromUser( $this->getTestUser( [ 'bot' ] )->getUser() ) );

		$anotherBot = ParserOptions::newFromUser( $this->getMutableTestUser( [ 'bot' ] )->getUser() );

		$this->assertNotFalse( $this->getServiceContainer()->getParserCache()->get( $page, $anotherBot ) );
	}

}
