<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * A parser function can put SWS UI on any page, including one none of the frontend-loading page kinds cover.
 *
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onBeforePageDisplay
 * @group Database
 */
class ParserFunctionFrontendLoadingTest extends SWSIntegrationTestCase {

	public function testAddsTheFrontendConfigVarsWhenWikitextAskedForTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgSWSValidationDebounceMs', $out->getJsConfigVars() );
		$this->assertArrayHasKey( 'wgSWSEnforceValidation', $out->getJsConfigVars() );
	}

	public function testAddsNoFrontendConfigVarsWithoutTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries nothing' ), requestsTheFrontend: false );

		$this->assertArrayNotHasKey( 'wgSWSValidationDebounceMs', $out->getJsConfigVars() );
	}

	public function testAddsTheDeniedReasonWhenWikitextAskedForTheModule(): void {
		$out = $this->displayPage( Title::makeTitle( NS_HELP, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgSWSCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsTheDeniedReasonToAContentPageCarryingAButton(): void {
		$out = $this->displayPage( Title::makeTitle( NS_MAIN, 'Carries a button' ), requestsTheFrontend: true );

		$this->assertArrayHasKey( 'wgSWSCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsNoDeniedReasonToAContentPageWithoutAButton(): void {
		$out = $this->displayPage( Title::makeTitle( NS_MAIN, 'Carries nothing' ), requestsTheFrontend: false );

		$this->assertArrayNotHasKey( 'wgSWSCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	public function testAddsNoDeniedReasonToASpecialPageThatLoadedTheFrontend(): void {
		$out = $this->displayPage( Title::makeTitle( NS_SPECIAL, 'Schemas' ), requestsTheFrontend: true );

		$this->assertArrayNotHasKey( 'wgSWSCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
	}

	private function displayPage( Title $title, bool $requestsTheFrontend ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( $title );

		$out = $context->getOutput();
		$out->setArticleFlag( !$title->isSpecialPage() );

		if ( $requestsTheFrontend ) {
			// What a parser function's ParserOutput, or a special page loading the frontend itself, has added by then.
			$out->addModules( [ 'ext.sws' ] );
		}

		SWSHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

}
