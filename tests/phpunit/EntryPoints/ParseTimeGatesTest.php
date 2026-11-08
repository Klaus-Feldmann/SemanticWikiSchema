<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Parser\ParserOptions;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\ParseTimePermissionFixtures;

/**
 * Parse-time reads run as the user the page is parsed for, not as the request's user. The request
 * belongs to a sysop throughout; only the parser options decide what a parse gets.
 *
 * @covers \ProfessionalWiki\SWS\EntryPoints\ParserAuthority
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSValueParserFunction
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\EntryPoints\ParserFunction\CypherRawParserFunction
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\ParserFunction\SparqlRawParserFunction
 * @group Database
 */
class ParseTimeGatesTest extends SWSIntegrationTestCase {

	use ParseTimePermissionFixtures;

	private const string RESTRICTED_PAGE = 'ParseTimeGatesRestrictedPage';
	private const string SECRET = 'Only sysops may read this';

	protected function setUp(): void {
		parent::setUp();

		RequestContext::getMain()->setUser( $this->getTestSysop()->getUser() );

		$this->createPageWithSubjects(
			self::RESTRICTED_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( self::SECRET ) ),
			] ) )
		);
		$this->denyAnonymousReadOf( self::RESTRICTED_PAGE );
	}

	private function parseAs( ParserOptions $parserOptions, string $wikitext ): string {
		return $this->parseWikitextOn( 'ParseTimeGatesTestPage', $wikitext, $parserOptions );
	}

	private function asSysop(): ParserOptions {
		return ParserOptions::newFromUser( $this->getTestSysop()->getUser() );
	}

	private function valueFromRestrictedPage(): string {
		return '{{#sws_value: Motto | page=' . self::RESTRICTED_PAGE . ' }}';
	}

	public function testAnonymousParseDoesNotReadTheRestrictedPageEvenThoughTheRequestIsASysops(): void {
		$html = $this->parseAs( ParserOptions::newFromAnon(), $this->valueFromRestrictedPage() );

		$this->assertStringNotContainsString( self::SECRET, $html );
	}

	public function testParseForAReaderOfThePageReadsIt(): void {
		$html = $this->parseAs( $this->asSysop(), $this->valueFromRestrictedPage() );

		$this->assertStringContainsString( self::SECRET, $html );
	}

	public function testAnonymousParseWithoutTheQueryRightRendersAnErrorEvenThoughTheRequestIsASysops(): void {
		$this->grantTheQueryRightToSysopsOnly();

		$html = $this->parseAs( ParserOptions::newFromAnon(), '{{#cypher_raw: RETURN 1 AS n }}' );

		$this->assertStringContainsString( 'You do not have permission to run Cypher queries.', $html );
		$this->assertStringNotContainsString( 'mw-sws-cypher-result', $html );
	}

	public function testParseForAUserWithTheQueryRightRunsTheQuery(): void {
		$this->setUpNeo4j();
		$this->grantTheQueryRightToSysopsOnly();

		$html = $this->parseAs( $this->asSysop(), '{{#cypher_raw: RETURN 1 AS n }}' );

		$this->assertStringContainsString( 'mw-sws-cypher-result', $html );
	}

	public function testParseTimeQueriesUseTheDefaultTierEvenForAUserWithHighLimits(): void {
		$this->setUpNeo4j();
		$this->capTheDefaultTierAtOneRow();

		$html = html_entity_decode( $this->parseAs( $this->asSysop(), '{{#cypher_raw: UNWIND [1, 2] AS n RETURN n }}' ) );

		$this->assertStringContainsString( '"n": 1', $html );
		$this->assertStringNotContainsString( '"n": 2', $html );
	}

	public function testAnonymousParseWithoutTheQueryRightRendersASparqlErrorEvenThoughTheRequestIsASysops(): void {
		$this->configureAnUnreachableSparqlStore();
		$this->grantTheQueryRightToSysopsOnly();

		$html = $this->parseAs( ParserOptions::newFromAnon(), '{{#sparql_raw: SELECT * WHERE { ?s ?p ?o } }}' );

		$this->assertStringContainsString( 'You do not have permission to run SPARQL queries.', $html );
		$this->assertStringNotContainsString( 'mw-sws-sparql-result', $html );
	}

}
