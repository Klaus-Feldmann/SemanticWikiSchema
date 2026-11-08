<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Sparql;

use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * The REST route's presence is covered by SparqlRouteRegistrationTest and OnExtensionRegistrationTest.
 *
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\SparqlPlugin
 * @covers \ProfessionalWiki\SWS\SWSExtension::getFirstSparqlPlugin
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onParserFirstCallInit
 * @group Database
 */
class SparqlSurfaceRegistrationTest extends SWSIntegrationTestCase {

	protected function tearDown(): void {
		// The singleton bakes the store config; reset it so the next test rebuilds from restored config.
		SWSExtension::resetInstance();
		parent::tearDown();
	}

	public function testParserFunctionExplainsItCannotRunWithoutStore(): void {
		$this->clearSparqlStores();

		$html = $this->parseWikitextOn(
			'NoSparqlStorePage',
			'{{#sparql_raw: SELECT ?s WHERE { ?s ?p ?o } }}',
			$this->parserOptionsShowingMessageKeys()
		);

		$this->assertStringContainsString( '(sws-sparql-raw-error-not-configured)', $html );
	}

	public function testLuaFunctionOfferedWhenStoreConfigured(): void {
		$this->configureSparqlStore();

		$names = SWSExtension::getInstance()->getFirstSparqlPlugin()?->getLuaLibraryFunctionNames() ?? [];

		$this->assertContains( 'sparqlQuery', $names );
	}

	public function testLuaFunctionNotOfferedWithoutStore(): void {
		$this->clearSparqlStores();

		$this->assertNull( SWSExtension::getInstance()->getFirstSparqlPlugin() );
	}

	private function configureSparqlStore(): void {
		$this->overrideConfigValue( 'SWSSparqlStores', [ [ 'updateUrl' => 'https://qlever.example/api' ] ] );
		SWSExtension::resetInstance();
	}

	private function clearSparqlStores(): void {
		$this->overrideConfigValue( 'SWSSparqlStores', [] );
		SWSExtension::resetInstance();
	}

}
