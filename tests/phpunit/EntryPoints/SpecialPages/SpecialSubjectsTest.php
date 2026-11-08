<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\SpecialPages;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\SpecialPage\SpecialPage;
use ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialSubjects;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialSubjects
 * @group Database
 */
class SpecialSubjectsTest extends SWSIntegrationTestCase {

	use HelpLinkAssertions;

	public function testMountsTheList(): void {
		$this->assertStringContainsString( 'id="ext-sws-subjects"', $this->outputFor( null ) );
	}

	public function testPassesTheSubpageOnAsTheInitialSchemaInItsCanonicalForm(): void {
		$this->assertStringContainsString(
			'data-mw-sws-schema="Computer model"',
			$this->outputFor( 'computer_model' )
		);
	}

	public function testKeepsANamespacePrefixAsPartOfTheSchemaName(): void {
		$this->assertStringContainsString( 'data-mw-sws-schema="Help:Person"', $this->outputFor( 'Help:Person' ) );
	}

	public function testStartsOnEverySchemaWithoutASubpage(): void {
		$this->assertStringNotContainsString( 'data-mw-sws-schema', $this->outputFor( null ) );
	}

	public function testStartsOnEverySchemaWithAnUnparseableSubpage(): void {
		$this->assertStringNotContainsString( 'data-mw-sws-schema', $this->outputFor( '<' ) );
	}

	public function testMountsTheListWithoutAGraphBackend(): void {
		$this->assertStringContainsString(
			'id="ext-sws-subjects"',
			$this->runWithoutGraphBackend( fn (): string => $this->outputFor( null ) )
		);
	}

	public function testLoadsTheFrontendModule(): void {
		$this->assertContains( 'ext.sws', $this->outputPageFor( null )->getModules() );
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( new SpecialSubjects() ) );
	}

	public function testThePageIsListedForEveryone(): void {
		$this->assertArrayHasKey(
			'Subjects',
			$this->getServiceContainer()->getSpecialPageFactory()
				->getUsablePages( RequestContext::getMain()->getUser(), RequestContext::getMain() )
		);
	}

	private function outputFor( ?string $subPage ): string {
		return $this->outputPageFor( $subPage )->getHTML();
	}

	private function outputPageFor( ?string $subPage ): OutputPage {
		$context = new RequestContext();
		$context->setTitle( SpecialPage::getTitleFor( 'Subjects' ) );
		$context->setAuthority( $this->getTestUser()->getAuthority() );
		$context->setLanguage( 'qqx' );

		$page = new SpecialSubjects();
		$page->setContext( $context );
		$page->execute( $subPage );

		return $context->getOutput();
	}

}
