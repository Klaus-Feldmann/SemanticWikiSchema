<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\Search;

use MediaWiki\MainConfigNames;
use ProfessionalWiki\SWS\EntryPoints\Search\SearchMySQLWithSubjects;
use ProfessionalWiki\SWS\EntryPoints\Search\SearchSqliteWithSubjects;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use SearchEngine;
use SearchEngineDummy;

/**
 * Which search engine a wiki with SWS installed ends up with. MediaWiki resolves the configured
 * engine, or the one it picks from the database when none is configured, through the SearchMappings
 * entries in extension.json, which is where SWS puts its own engines in place of core's.
 *
 * The test framework configures SearchEngineDummy, so every case states the configuration it is about.
 *
 * @group Database
 * @covers \ProfessionalWiki\SWS\EntryPoints\Search\SearchMySQLWithSubjects
 * @covers \ProfessionalWiki\SWS\EntryPoints\Search\SearchSqliteWithSubjects
 */
class SearchEngineMappingTest extends SWSIntegrationTestCase {

	/**
	 * Per database type: the name an administrator writes core's engine down as, and SWS's
	 * stand-in for it.
	 */
	private const ENGINES = [
		'mysql' => [ 'SearchMySQL', SearchMySQLWithSubjects::class ],
		'sqlite' => [ 'SearchSqlite', SearchSqliteWithSubjects::class ],
	];

	protected function setUp(): void {
		parent::setUp();

		if ( !array_key_exists( $this->getDb()->getType(), self::ENGINES ) ) {
			$this->markTestSkipped( 'SWS stands in for the MySQL and SQLite search engines only' );
		}
	}

	public function testWikiWithoutAConfiguredEngineIndexesSubjects(): void {
		$this->overrideConfigValue( MainConfigNames::SearchType, null );

		$this->assertInstanceOf( $this->subjectIndexingEngine(), $this->createSearchEngine() );
	}

	public function testConfiguringCoresDatabaseEngineIndexesSubjects(): void {
		$this->overrideConfigValue( MainConfigNames::SearchType, $this->coreEngineName() );

		$this->assertInstanceOf( $this->subjectIndexingEngine(), $this->createSearchEngine() );
	}

	public function testAnotherConfiguredEngineIsLeftAlone(): void {
		$this->overrideConfigValue( MainConfigNames::SearchType, SearchEngineDummy::class );

		$this->assertInstanceOf( SearchEngineDummy::class, $this->createSearchEngine() );
	}

	private function coreEngineName(): string {
		return self::ENGINES[$this->getDb()->getType()][0];
	}

	private function subjectIndexingEngine(): string {
		return self::ENGINES[$this->getDb()->getType()][1];
	}

	private function createSearchEngine(): SearchEngine {
		return $this->getServiceContainer()->getSearchEngineFactory()->create();
	}

}
