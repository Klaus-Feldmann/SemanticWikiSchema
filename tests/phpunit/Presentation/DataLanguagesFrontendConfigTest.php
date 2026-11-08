<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Presentation;

use MediaWiki\Content\JsonContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\SWSExtension;

/**
 * The data languages the frontend gets wherever it loads, as every page loading it goes through the
 * extension's frontend module loader. Needs the database for the MediaWiki:SWS configuration page.
 *
 * @covers \ProfessionalWiki\SWS\SWSExtension::getDataLanguages
 * @covers \ProfessionalWiki\SWS\Presentation\FrontendModuleLoader
 * @group Database
 */
class DataLanguagesFrontendConfigTest extends MediaWikiIntegrationTestCase {

	protected function tearDown(): void {
		// Drops the memoized configuration page, so a later test does not inherit it.
		SWSExtension::resetInstance();

		parent::tearDown();
	}

	public function testTheConfigurationPageDataLanguagesReachTheFrontend(): void {
		$this->overrideConfigValue( 'SWSDataLanguages', [ 'en' ] );
		$this->saveConfigPage( '{ "dataLanguages": [ "fr", "de", "it" ] }' );

		$this->assertSame( [ 'fr', 'de', 'it' ], $this->frontendDataLanguages() );
	}

	public function testMalformedLocalSettingsDataLanguagesReachTheFrontendAsNone(): void {
		$this->overrideConfigValue( 'SWSDataLanguages', 'de, fr' );

		$this->assertSame( [], $this->frontendDataLanguages() );
	}

	private function saveConfigPage( string $json ): void {
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, SWSExtension::CONFIG_PAGE_TITLE ), new JsonContent( $json ) );

		SWSExtension::resetInstance();
	}

	private function frontendDataLanguages(): mixed {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Data languages' ) );

		$out = $context->getOutput();
		SWSExtension::getInstance()->newFrontendModuleLoader()->load( $out, $context->getSkin() );

		return $out->getJsConfigVars()['wgSWSDataLanguages'] ?? null;
	}

}
