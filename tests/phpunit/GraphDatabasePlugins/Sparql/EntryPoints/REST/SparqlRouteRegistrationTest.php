<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Sparql\EntryPoints\REST;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\REST\SparqlRouteRegistration;

/**
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\EntryPoints\REST\SparqlRouteRegistration
 */
class SparqlRouteRegistrationTest extends TestCase {

	public function testReturnsRouteFileWhenAStoreIsConfigured(): void {
		$files = SparqlRouteRegistration::routeFiles( [ [ 'updateUrl' => 'https://qlever.example/api' ] ] );

		$this->assertCount( 1, $files );
		$this->assertStringEndsWith( 'sparqlRoutes.json', $files[0] );
		$this->assertFileExists( $files[0] );
	}

	public function testReturnsNothingWhenNoStoresConfigured(): void {
		$this->assertSame( [], SparqlRouteRegistration::routeFiles( [] ) );
		$this->assertSame( [], SparqlRouteRegistration::routeFiles( null ) );
	}

	public function testReturnsNothingWhenTheOnlyEntryLacksAnUpdateUrl(): void {
		$this->assertSame( [], SparqlRouteRegistration::routeFiles( [ [ 'accessToken' => 'secret' ] ] ) );
	}

	public function testRouteFileDeclaresSparqlRoute(): void {
		$routes = json_decode( file_get_contents( SparqlRouteRegistration::routeFiles( [ [ 'updateUrl' => 'u' ] ] )[0] ), true );

		$this->assertSame( '/sws/v0/query/sparql', $routes[0]['path'] );
		$this->assertSame( [ 'POST' ], $routes[0]['method'] );
		$this->assertSame(
			'ProfessionalWiki\\SWS\\SWSExtension::newSparqlQueryApi',
			$routes[0]['factory']
		);
	}

}
