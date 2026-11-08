<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\SWSMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onBeforePageDisplay
 * @group Database
 */
class SubjectEditPermissionHintTest extends SWSIntegrationTestCase {

	use SWSMockAuthorityTrait;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	public function testTellsTheFrontendTheViewerMayEditThePagesSubjects(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest editable' )->getTitle();

		$out = $this->renderContentPage( $title, $this->getTestSysop()->getAuthority() );

		$this->assertTrue( $out->getJsConfigVars()['wgSWSCanEditPageSubjects'] );
	}

	public function testTellsTheFrontendAViewerWhoMayNotEditThePageMayNotEditItsSubjects(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest protected' )->getTitle();

		$out = $this->renderContentPage(
			$title,
			$this->authorityThatCannotEditPageId( $title->getArticleID() )
		);

		$this->assertFalse(
			$out->getJsConfigVars()['wgSWSCanEditPageSubjects'],
			'The decision must be the one made for the page being viewed.'
		);
	}

	public function testOffersTheSubjectCreatorOnlyToAViewerWhoMayEditThePage(): void {
		$title = $this->getExistingTestPage( 'SubjectEditPermissionHintTest creator' )->getTitle();

		$editable = $this->renderContentPage( $title, $this->getTestSysop()->getAuthority() );
		$protected = $this->renderContentPage(
			$title,
			$this->authorityThatCannotEditPageId( $title->getArticleID() )
		);

		$this->assertStringContainsString( 'data-mw-sws-create-subject', $editable->getHTML() );
		$this->assertStringNotContainsString( 'data-mw-sws-create-subject', $protected->getHTML() );
	}

	private function renderContentPage( Title $title, Authority $authority ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setAuthority( $authority );

		$out = $context->getOutput();
		$out->setArticleFlag( true );
		$out->setRevisionId( $title->getLatestRevID() );

		SWSHooks::onBeforePageDisplay( $out, $context->getSkin() );

		return $out;
	}

}
