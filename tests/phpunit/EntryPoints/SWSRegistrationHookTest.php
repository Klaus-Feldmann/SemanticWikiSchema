<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Registration\ExtensionRegistry;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\RedHerb\RedHerbGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSRegistrar
 * @covers \ProfessionalWiki\SWS\SWSExtension
 * @group Database
 */
class SWSRegistrationHookTest extends MediaWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		if ( !ExtensionRegistry::getInstance()->isLoaded( 'RedHerb' ) ) {
			$this->markTestSkipped( 'RedHerb extension is not loaded' );
		}
	}

	public function testRedHerbRegistersPropertyType(): void {
		$registry = SWSExtension::getInstance()->getPropertyTypeRegistry();

		$this->assertNotNull( $registry->getType( 'color' ) );
	}

	public function testRedHerbRegistersPagePropertyProvider(): void {
		$providers = SWSExtension::getInstance()->getPagePropertyProviderRegistry()->getProviders();

		$this->assertGreaterThan( 1, count( $providers ), 'Should have more than just the core provider' );
	}

	public function testRedHerbRegistersNeo4jValueBuilder(): void {
		$registry = SWSExtension::getInstance()->getValueBuilderRegistry();

		$this->assertTrue( $registry->hasBuilder( 'color' ) );
	}

	public function testRedHerbRegistersRdfValueMapper(): void {
		$registry = SWSExtension::getInstance()->getRdfValueMapperRegistry();

		$this->assertTrue( $registry->hasMapper( 'color' ) );
	}

	public function testRedHerbRegistersGraphDatabasePlugin(): void {
		$plugins = SWSExtension::getInstance()->getGraphDatabasePluginRegistry()->getPlugins();

		// The registry holds extension-contributed plugins only; core Neo4j is composed separately.
		$this->assertCount( 1, $plugins );
		$this->assertContainsOnlyInstancesOf( RedHerbGraphDatabasePlugin::class, $plugins );
	}

}
