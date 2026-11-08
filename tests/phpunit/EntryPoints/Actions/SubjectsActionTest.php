<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\Actions;

use Article;
use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\EntryPoints\Actions\SubjectsAction;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\SWSMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\Actions\SubjectsAction
 * @group Database
 */
class SubjectsActionTest extends SWSIntegrationTestCase {

	use SWSMockAuthorityTrait;

	public function testNullTitleIsNotEligible(): void {
		$this->assertFalse( SubjectsAction::isEligibleTitle( null ) );
	}

	public function testNonExistentTitleIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 0 );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInNonContentNamespaceIsNotEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_USER_TALK );

		$this->assertFalse( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testTitleInContentNamespaceIsEligible(): void {
		$title = $this->createMock( Title::class );
		$title->method( 'canExist' )->willReturn( true );
		$title->method( 'getArticleID' )->willReturn( 1 );
		$title->method( 'getNamespace' )->willReturn( NS_MAIN );

		$this->assertTrue( SubjectsAction::isEligibleTitle( $title ) );
	}

	public function testExposesThatTheViewerMayEditTheSubjects(): void {
		$out = $this->runOnView( 'SubjectsActionTest editable', $this->getTestSysop()->getAuthority() );

		$this->assertTrue( $out->getJsConfigVars()['wgSWSCanEditPageSubjects'] );
	}

	public function testExposesThatAViewerWhoMayNotEditThePageMayNotEditItsSubjects(): void {
		$pageId = $this->getExistingTestPage( 'SubjectsActionTest protected' )->getId();

		$out = $this->runOnView(
			'SubjectsActionTest protected',
			$this->authorityThatCannotEditPageId( $pageId )
		);

		$this->assertFalse(
			$out->getJsConfigVars()['wgSWSCanEditPageSubjects'],
			'The decision must be the one made for the page whose Subjects are managed.'
		);
	}

	public function testExposesTheSubjectIriBaseAsConfigVar(): void {
		$this->overrideConfigValue( 'SWSRdfBaseUri', 'https://data.example.org' );
		SWSExtension::resetInstance();

		try {
			$out = $this->runOnView( 'SubjectsActionTest iri base', $this->getTestSysop()->getAuthority() );

			$this->assertSame(
				'https://data.example.org/entity/',
				$out->getJsConfigVars()['wgSWSSubjectIriBase']
			);
		} finally {
			SWSExtension::resetInstance();
		}
	}

	private function runOnView( string $pageName, Authority $authority ): OutputPage {
		$title = $this->getExistingTestPage( $pageName )->getTitle();

		$context = new RequestContext();
		$context->setTitle( $title );
		$context->setAuthority( $authority );

		( new SubjectsAction( Article::newFromTitle( $title, $context ), $context ) )->onView();

		return $context->getOutput();
	}

}
