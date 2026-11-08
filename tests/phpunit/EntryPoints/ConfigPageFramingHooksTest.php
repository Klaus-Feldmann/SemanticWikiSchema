<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onAlternateEdit
 * @group Database
 */
class ConfigPageFramingHooksTest extends MediaWikiIntegrationTestCase {

	private function configTitle(): Title {
		return Title::makeTitle( NS_MEDIAWIKI, 'SWS' );
	}

	private function englishContext( Title $title ): RequestContext {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		return $context;
	}

	private function runAlternateEdit( Title $title ): EditPage {
		$editPage = new EditPage( Article::newFromTitle( $title, $this->englishContext( $title ) ) );
		SWSHooks::onAlternateEdit( $editPage );

		return $editPage;
	}

	public function testConfigPageEditIsFramedWithTheReference(): void {
		$editPage = $this->runAlternateEdit( $this->configTitle() );

		$this->assertTrue( $editPage->suppressIntro );
		$this->assertStringContainsString( 'sws.ai', $editPage->editFormTextTop );
		$this->assertStringContainsString( '$wgSWSSubjectFirst', $editPage->editFormTextBottom );
	}

	public function testOtherMediaWikiPageEditIsNotFramed(): void {
		$editPage = $this->runAlternateEdit( Title::makeTitle( NS_MEDIAWIKI, 'NotSWS' ) );

		$this->assertFalse( $editPage->suppressIntro );
		$this->assertSame( '', $editPage->editFormTextBottom );
	}

	public function testConfigPageEditIsNotFramedWhenInWikiConfigDisabled(): void {
		$this->overrideConfigValue( 'SWSEnableInWikiConfig', false );

		$editPage = $this->runAlternateEdit( $this->configTitle() );

		$this->assertFalse( $editPage->suppressIntro );
		$this->assertSame( '', $editPage->editFormTextBottom );
	}

}
