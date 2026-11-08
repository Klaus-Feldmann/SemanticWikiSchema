<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\EditPage\EditPage;
use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\SWSExtension;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onAlternateEdit
 * @group Database
 */
class MappingPageEditDocsPointerTest extends MediaWikiIntegrationTestCase {

	private function runAlternateEdit( Title $title ): EditPage {
		$context = new RequestContext();
		$context->setLanguage( 'en' );
		$context->setTitle( $title );

		$editPage = new EditPage( Article::newFromTitle( $title, $context ) );
		SWSHooks::onAlternateEdit( $editPage );

		return $editPage;
	}

	private function mappingTitle(): Title {
		return Title::makeTitle( SWSExtension::NS_MAPPING, 'EDM' );
	}

	public function testMappingPageEditLinksTheMappingFormatDocumentation(): void {
		$editPage = $this->runAlternateEdit( $this->mappingTitle() );

		$this->assertStringContainsString(
			'href="https://neowiki.ai/docs/authoring/mapping-format"',
			$editPage->editFormTextTop
		);
		$this->assertFalse( $editPage->suppressIntro, 'Core\'s edit intro must survive, unlike on the config page.' );
	}

	public static function namespaceWithoutThePointerProvider(): iterable {
		yield 'the Mapping talk namespace' => [ NS_SWS_MAPPING_TALK ];
		yield 'the Schema namespace' => [ SWSExtension::NS_SCHEMA ];
		yield 'the main namespace' => [ NS_MAIN ];
	}

	/**
	 * @dataProvider namespaceWithoutThePointerProvider
	 */
	public function testEditOutsideTheMappingNamespaceCarriesNoPointer( int $namespace ): void {
		$this->assertSame( '', $this->runAlternateEdit( Title::makeTitle( $namespace, 'EDM' ) )->editFormTextTop );
	}

}
