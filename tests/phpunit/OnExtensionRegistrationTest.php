<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\SWSExtension;

/**
 * @covers \ProfessionalWiki\SWS\SWSExtension::onExtensionRegistration
 * @covers \ProfessionalWiki\SWS\SWSExtension::poweredByBadge
 * @covers \ProfessionalWiki\SWS\SWSExtension::requireComposerLibraries
 */
class OnExtensionRegistrationTest extends TestCase {

	use HandlesNeo4jEnvOverrides;

	/**
	 * The globals registration writes to, restored after every test.
	 */
	private const CHANGED_GLOBALS = [
		'wgRestAPIAdditionalRouteFiles',
		'wgSWSNeo4jInternalWriteUrl',
		'wgSWSNeo4jInternalReadUrl',
		'wgSWSSparqlStores',
		'wgFooterIcons',
		'wgExtensionAssetsPath',
		'wgCirrusSearchWeights',
		'wgNamespacesToBeSearchedDefault',
	];

	/**
	 * @var array<string, mixed>
	 */
	private array $globalsBefore = [];

	protected function setUp(): void {
		parent::setUp();

		foreach ( self::CHANGED_GLOBALS as $name ) {
			$this->globalsBefore[$name] = $GLOBALS[$name] ?? null;
		}

		// Clear the CI env overrides so the config-value path is exercised deterministically.
		$this->snapshotAndClearNeo4jEnvOverrides();
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = [];
		$GLOBALS['wgSWSSparqlStores'] = null;
	}

	protected function tearDown(): void {
		$this->restoreNeo4jEnvOverrides();

		foreach ( $this->globalsBefore as $name => $value ) {
			$GLOBALS[$name] = $value;
		}

		parent::tearDown();
	}

	public function testAddsCypherRouteFileWhenConfigured(): void {
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = 'bolt://read:7687';

		SWSExtension::onExtensionRegistration();

		$this->assertCount( 1, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertStringEndsWith( 'neo4jRoutes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'][0] );
	}

	public function testAddsNoRouteFileWhenUnconfigured(): void {
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = null;

		SWSExtension::onExtensionRegistration();

		$this->assertSame( [], $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testPreservesExistingRouteFiles(): void {
		$GLOBALS['wgRestAPIAdditionalRouteFiles'] = [ '/existing/routes.json' ];
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = 'bolt://read:7687';

		SWSExtension::onExtensionRegistration();

		$this->assertContains( '/existing/routes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertCount( 2, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testAddsSparqlRouteFileWhenStoreConfigured(): void {
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = null;
		$GLOBALS['wgSWSSparqlStores'] = [ [ 'updateUrl' => 'https://qlever.example/api' ] ];

		SWSExtension::onExtensionRegistration();

		$this->assertCount( 1, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
		$this->assertStringEndsWith( 'sparqlRoutes.json', $GLOBALS['wgRestAPIAdditionalRouteFiles'][0] );
	}

	public function testAddsNoSparqlRouteFileWithoutStore(): void {
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = null;
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = null;
		$GLOBALS['wgSWSSparqlStores'] = [];

		SWSExtension::onExtensionRegistration();

		$this->assertSame( [], $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testAddsBothRouteFilesWhenNeo4jAndSparqlConfigured(): void {
		$GLOBALS['wgSWSNeo4jInternalWriteUrl'] = 'bolt://write:7687';
		$GLOBALS['wgSWSNeo4jInternalReadUrl'] = 'bolt://read:7687';
		$GLOBALS['wgSWSSparqlStores'] = [ [ 'updateUrl' => 'https://qlever.example/api' ] ];

		SWSExtension::onExtensionRegistration();

		$this->assertCount( 2, $GLOBALS['wgRestAPIAdditionalRouteFiles'] );
	}

	public function testRegistersThePoweredByBadgeForTheRunningMediaWiki(): void {
		$this->givenOnlyTheMediaWikiBadge();

		SWSExtension::onExtensionRegistration();

		$this->assertSame(
			SWSExtension::poweredByBadge( '/w/extensions', MW_VERSION ),
			$GLOBALS['wgFooterIcons']['poweredbysws']['sws']
		);
	}

	public function testBadgeBeforeMediaWiki144IsTheFullBadgeOnly(): void {
		$this->assertSame(
			[
				'src' => '/w/extensions/SemanticWikiSchema/resources/images/poweredby_semantic_wikischema.svg',
				'url' => 'https://github.com/Klaus-Feldmann/SemanticWikiSchema',
				'alt' => 'Powered by Semantic WikiSchema',
				'lang' => 'en',
			],
			SWSExtension::poweredByBadge( '/w/extensions', '1.43.8' )
		);
	}

	public function testBadgeFromMediaWiki144ShowsTheCompactIconOnNarrowScreens(): void {
		$this->assertSame(
			[
				'src' => '/w/extensions/SemanticWikiSchema/resources/images/poweredby_semantic_wikischema_compact.svg',
				'url' => 'https://github.com/Klaus-Feldmann/SemanticWikiSchema',
				'alt' => 'Powered by Semantic WikiSchema',
				'lang' => 'en',
				'width' => 25,
				'height' => 25,
				'sources' => [
					[
						'media' => '(min-width: 500px)',
						'srcset' => '/w/extensions/SemanticWikiSchema/resources/images/poweredby_semantic_wikischema.svg',
						'width' => 88,
						'height' => 31,
					],
				],
			],
			SWSExtension::poweredByBadge( '/w/extensions', '1.44.0' )
		);
	}

	public function testMediaWiki144DevelopmentBuildsGetTheCompactIcon(): void {
		$this->assertArrayHasKey( 'sources', SWSExtension::poweredByBadge( '/w/extensions', '1.44.0-alpha' ) );
	}

	public function testAddsTheBadgeAsItsOwnBlockAfterTheMediaWikiOne(): void {
		$this->givenOnlyTheMediaWikiBadge();

		SWSExtension::onExtensionRegistration();

		$this->assertSame( [ 'poweredby', 'poweredbysws' ], array_keys( $GLOBALS['wgFooterIcons'] ) );
	}

	public function testKeepsAPreconfiguredPoweredByBadge(): void {
		$this->givenOnlyTheMediaWikiBadge();
		$GLOBALS['wgFooterIcons']['poweredbysws']['sws'] = false;

		SWSExtension::onExtensionRegistration();

		$this->assertFalse( $GLOBALS['wgFooterIcons']['poweredbysws']['sws'] );
	}

	public function testBadgeImagesExistWhereTheyAreRegistered(): void {
		$badge = SWSExtension::poweredByBadge( '/w/extensions', '1.44.0' );

		$this->assertFileExists( $this->fileBehind( $badge['src'] ) );
		$this->assertFileExists( $this->fileBehind( $badge['sources'][0]['srcset'] ) );
	}

	public function testStopsWithComposerInstructionsWhenItsLibrariesAreMissing(): void {
		$this->expectException( Exception::class );
		$this->expectExceptionMessage( 'composer update' );

		SWSExtension::requireComposerLibraries( 'ProfessionalWiki\SWS\Tests\NoSuchLibraryClass' );
	}

	public function testWeightsTheSubjectFieldIntoCirrusSearchQueries(): void {
		$GLOBALS['wgCirrusSearchWeights'] = [ 'text' => 1 ];

		SWSExtension::onExtensionRegistration();

		$this->assertSame( [ 'text' => 1, 'sws_text' => 1 ], $GLOBALS['wgCirrusSearchWeights'] );
	}

	public function testKeepsTheSubjectFieldWeightTheAdministratorConfigured(): void {
		$GLOBALS['wgCirrusSearchWeights'] = [ 'sws_text' => 5 ];

		SWSExtension::onExtensionRegistration();

		$this->assertSame( [ 'sws_text' => 5 ], $GLOBALS['wgCirrusSearchWeights'] );
	}

	public function testAddsNoWeightWithoutCirrusSearch(): void {
		$GLOBALS['wgCirrusSearchWeights'] = null;

		SWSExtension::onExtensionRegistration();

		$this->assertNull( $GLOBALS['wgCirrusSearchWeights'] );
	}

	public function testSearchesTheSubjectNamespaceByDefault(): void {
		$GLOBALS['wgNamespacesToBeSearchedDefault'] = [ NS_MAIN => true ];

		SWSExtension::onExtensionRegistration();

		$this->assertTrue( $GLOBALS['wgNamespacesToBeSearchedDefault'][SWSExtension::NS_SUBJECT] );
		$this->assertTrue( $GLOBALS['wgNamespacesToBeSearchedDefault'][NS_MAIN] );
	}

	public function testKeepsTheSubjectNamespaceSearchTheAdministratorConfigured(): void {
		$GLOBALS['wgNamespacesToBeSearchedDefault'] = [ NS_MAIN => true, SWSExtension::NS_SUBJECT => false ];

		SWSExtension::onExtensionRegistration();

		$this->assertFalse( $GLOBALS['wgNamespacesToBeSearchedDefault'][SWSExtension::NS_SUBJECT] );
	}

	private function fileBehind( string $url ): string {
		return dirname( __DIR__, 2 ) . str_replace( '/w/extensions/SemanticWikiSchema', '', $url );
	}

	private function givenOnlyTheMediaWikiBadge(): void {
		$GLOBALS['wgExtensionAssetsPath'] = '/w/extensions';
		$GLOBALS['wgFooterIcons'] = [
			'poweredby' => [
				'mediawiki' => [
					'src' => null,
					'url' => 'https://www.mediawiki.org/',
					'alt' => 'Powered by MediaWiki',
				],
			],
		];
	}

}
