<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests;

use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Neo4jPlugin;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\TestDoubles\SpyGraphDatabasePlugin;
use TestLogger;

/**
 * Every configured graph database backend is addressable by name, which is what scopes a rebuild to
 * one store.
 *
 * @covers \ProfessionalWiki\SWS\SWSExtension
 * @group Database
 */
class NamedGraphDatabasePluginsTest extends SWSIntegrationTestCase {

	protected function tearDown(): void {
		parent::tearDown();
		// The tests rebuild the singleton from overridden config and temporary hooks; reset it so later
		// tests get an instance built from the real ones.
		SWSExtension::resetInstance();
	}

	public function testNeo4jIsNamedAfterItsBackend(): void {
		$this->assertArrayHasKey(
			Neo4jPlugin::STORE_NAME,
			SWSExtension::getInstance()->getNamedGraphDatabasePlugins()
		);
	}

	public function testEachConfiguredStoreIsAddressableByName(): void {
		$this->configureSparqlStores( [
			[ 'updateUrl' => 'https://qlever.example/api' ],
			[ 'updateUrl' => 'https://qlever.example/api', 'projection' => 'EDM' ],
			[ 'updateUrl' => 'https://other.example/api', 'projection' => 'EDM', 'name' => 'edm-archive' ],
		] );
		$this->registerNamedGraphDatabasePlugins( [ 'redherb' => new SpyGraphDatabasePlugin() ] );

		$this->assertSame(
			[ 'neo4j', 'native', 'EDM', 'edm-archive', 'redherb' ],
			array_keys( SWSExtension::getInstance()->getNamedGraphDatabasePlugins() ),
			'the bundled backends come first, in configuration order, then the extension plugins'
		);
	}

	public function testEachNameResolvesToItsOwnBackend(): void {
		$spy = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ 'redherb' => $spy ] );

		$plugins = SWSExtension::getInstance()->getNamedGraphDatabasePlugins();

		$this->assertSame( $spy, $plugins['redherb'] );
		$this->assertNotSame( $spy, $plugins[Neo4jPlugin::STORE_NAME] );
	}

	public function testAnExtensionCannotShadowABundledBackend(): void {
		$logger = new TestLogger( true, null, true );
		$this->setLogger( 'SWS', $logger );
		$spy = new SpyGraphDatabasePlugin();
		$this->registerNamedGraphDatabasePlugins( [ Neo4jPlugin::STORE_NAME => $spy ] );

		$plugins = SWSExtension::getInstance()->getNamedGraphDatabasePlugins();
		$warnings = self::warnings( $logger );

		$this->assertNotSame( $spy, $plugins[Neo4jPlugin::STORE_NAME] );
		$this->assertCount( 1, $warnings, 'the plugin is dropped with a warning rather than in silence' );
		$this->assertStringContainsString( 'held by a bundled backend', $warnings[0][1] );
		$this->assertSame(
			[ 'name' => Neo4jPlugin::STORE_NAME ],
			$warnings[0][2],
			'the warning names the store the plugin tried to take'
		);
	}

	/**
	 * @return array[]
	 */
	private static function warnings( TestLogger $logger ): array {
		return array_values(
			array_filter( $logger->getBuffer(), static fn ( array $record ): bool => $record[0] === 'warning' )
		);
	}

	/**
	 * @param array<int, array<string, string>> $stores
	 */
	private function configureSparqlStores( array $stores ): void {
		$this->overrideConfigValue( 'SWSSparqlStores', $stores );
		SWSExtension::resetInstance();
	}

}
