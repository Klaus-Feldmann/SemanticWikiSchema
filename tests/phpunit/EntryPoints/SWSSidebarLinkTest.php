<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Context\RequestContext;
use MediaWiki\Title\Title;
use MediaWiki\User\User;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onSidebarBeforeOutput
 * @group Database
 */
class SWSSidebarLinkTest extends SWSIntegrationTestCase {

	private const SWS_SECTION = 'sws-page-tools-label';

	public function testOverviewLinkIsListedFirst(): void {
		$section = $this->swsSectionOnAnOrdinaryPage();
		$link = reset( $section );

		$this->assertSame( 't-sws-overview', $link['id'] );
		$this->assertSame( 'Overview', $link['text'] );
		$this->assertStringContainsString( 'SWS', $link['href'] );
	}

	public function testOverviewLinkIsShownToAUserWhoMayNotCreatePages(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->assertNotNull(
			$this->findLinkById( $this->swsSectionOnAnOrdinaryPage(), 't-sws-overview' )
		);
	}

	public function testOverviewLinkIsShownOutsideTheContentNamespaces(): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( NS_HELP, 'Example' ) );

		$this->assertNotNull( $this->findLinkById( $sidebar[self::SWS_SECTION] ?? [], 't-sws-overview' ) );
	}

	public function testAllSchemasLinkIsPlacedInTheSWSSection(): void {
		$this->assertAllPagesLinkInSWSSection(
			namespace: SWSExtension::NS_SCHEMA,
			linkId: 't-sws-schemas',
			text: 'All schemas',
			hrefContains: 'Schemas',
			hrefExcludes: 'Layouts'
		);
	}

	public function testAllLayoutsLinkIsPlacedInTheSWSSection(): void {
		$this->assertAllPagesLinkInSWSSection(
			namespace: SWSExtension::NS_LAYOUT,
			linkId: 't-sws-layouts',
			text: 'All layouts',
			hrefContains: 'Layouts',
			hrefExcludes: 'Schemas'
		);
	}

	public function testAllMappingsLinkIsPlacedInTheSWSSection(): void {
		$this->assertAllPagesLinkInSWSSection(
			namespace: SWSExtension::NS_MAPPING,
			linkId: 't-sws-mappings',
			text: 'All mappings',
			hrefContains: 'Mappings',
			hrefExcludes: 'Schemas'
		);
	}

	public function testAddsNoAllPagesLinkOutsideSWSNamespaces(): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( NS_MAIN, 'Ordinary Page' ) );

		$this->assertNull( $this->findLinkById( $sidebar[self::SWS_SECTION] ?? [], 't-sws-schemas' ) );
		$this->assertNull( $this->findLinkById( $sidebar[self::SWS_SECTION] ?? [], 't-sws-layouts' ) );
		$this->assertNull( $this->findLinkById( $sidebar[self::SWS_SECTION] ?? [], 't-sws-mappings' ) );
	}

	public function testCreateSubjectLinkIsPlacedInTheSWSSection(): void {
		$link = $this->findLinkById( $this->swsSectionOnAnOrdinaryPage(), 't-sws-create-subject-page' );

		$this->assertNotNull( $link, 'Expected the create-subject link in the SWS sidebar section.' );
		$this->assertSame( 'Create subject', $link['text'] );
		$this->assertStringContainsString( 'CreateSubject', $link['href'] );
	}

	public function testCreateSubjectLinkIsListedAfterThePageTools(): void {
		$section = $this->swsSectionOnAnOrdinaryPage();

		$this->assertSame( 't-sws-create-subject-page', end( $section )['id'] );
	}

	public function testCreateSubjectLinkIsAbsentForAUserWhoMayNotCreatePages(): void {
		$this->setGroupPermissions( '*', 'createpage', false );
		$this->setGroupPermissions( 'user', 'createpage', false );

		$this->assertNull(
			$this->findLinkById( $this->swsSectionOnAnOrdinaryPage(), 't-sws-create-subject-page' )
		);
	}

	public function testCreateSubjectLinkIsAbsentForAUserWhoMayNotEdit(): void {
		$this->setGroupPermissions( '*', 'edit', false );
		$this->setGroupPermissions( 'user', 'edit', false );

		$this->assertNull(
			$this->findLinkById( $this->swsSectionOnAnOrdinaryPage(), 't-sws-create-subject-page' )
		);
	}

	public function testCreateSubjectLinkIsAbsentWhereThePageToolsOpenTheSubjectCreator(): void {
		$section = $this->swsSectionOnAPageTheCreatorOpensOn();

		$this->assertNotNull(
			$this->findLinkById( $section, 't-sws-create-subject' ),
			'Expected the page tool that opens the Subject creator on the page being viewed.'
		);
		$this->assertNull(
			$this->findLinkById( $section, 't-sws-create-subject-page' ),
			'The special page must not be linked where the page tools already open the creator.'
		);
	}

	public function testCreateSubjectLinkIsShownOutsideTheContentNamespaces(): void {
		$this->assertNotNull(
			$this->findLinkById(
				$this->swsSection( Title::makeTitle( NS_HELP, 'Example' ) ),
				't-sws-create-subject-page'
			)
		);
	}

	public function testPageToolsAreAbsentOutsideTheContentNamespaces(): void {
		$page = $this->getExistingTestPage( Title::makeTitle( NS_HELP, 'Existing Help Page' ) );

		$this->assertSame(
			[ 't-sws-overview', 't-sws-create-subject-page' ],
			array_column( $this->swsSection( $page->getTitle(), $page->getLatest() ), 'id' )
		);
	}

	public function testSpecialPagesShowTheLinksThatDescribeNoPage(): void {
		$this->assertSame(
			[ 't-sws-overview', 't-sws-create-subject-page' ],
			array_column( $this->swsSectionOnASpecialPage(), 'id' )
		);
	}

	/**
	 * Core shows Special:Badtitle for a title the user may not read, so the handler meets it on ordinary requests.
	 */
	public function testOverviewLinkIsShownOnTheBadtitlePage(): void {
		$this->assertNotNull(
			$this->findLinkById(
				$this->swsSection( Title::makeTitle( NS_SPECIAL, 'Badtitle' ) ),
				't-sws-overview'
			)
		);
	}

	private function assertAllPagesLinkInSWSSection(
		int $namespace,
		string $linkId,
		string $text,
		string $hrefContains,
		string $hrefExcludes
	): void {
		$sidebar = $this->buildSidebar( Title::makeTitle( $namespace, 'Example' ) );

		$link = $this->findLinkById( $sidebar[self::SWS_SECTION] ?? [], $linkId );

		$this->assertNotNull( $link, "Expected the $linkId link in the SWS sidebar section." );
		$this->assertSame( $text, $link['text'] );
		$this->assertStringContainsString( $hrefContains, $link['href'] );
		$this->assertStringNotContainsString( $hrefExcludes, $link['href'] );

		$this->assertNull(
			$this->findLinkById( $sidebar['TOOLBOX'] ?? [], $linkId ),
			"The $linkId link must not be in the generic Tools section."
		);
	}

	/**
	 * The page tools hold the RDF link whatever the configuration, and not the Subject creator, so the section
	 * also links the special page.
	 */
	private function swsSectionOnAnOrdinaryPage(): array {
		$page = $this->getExistingTestPage( Title::makeTitle( NS_MAIN, 'Ordinary Page' ) );

		return $this->swsSection( $page->getTitle() );
	}

	/**
	 * The page tools offer the creator on a content page that exists and is shown at its latest revision.
	 */
	private function swsSectionOnAPageTheCreatorOpensOn(): array {
		$page = $this->getExistingTestPage( Title::makeTitle( NS_MAIN, 'Existing Page' ) );

		return $this->swsSection( $page->getTitle(), $page->getLatest() );
	}

	private function swsSectionOnASpecialPage(): array {
		return $this->swsSection( Title::makeTitle( NS_SPECIAL, 'Subject' ) );
	}

	private function swsSection( Title $title, ?int $revisionId = null ): array {
		$sidebar = $this->buildSidebar( $title, $this->getTestUser()->getUser(), $revisionId );

		return $sidebar[self::SWS_SECTION] ?? [];
	}

	private function buildSidebar( Title $title, ?User $user = null, ?int $revisionId = null ): array {
		$context = new RequestContext();
		$context->setTitle( $title );

		if ( $user !== null ) {
			$context->setUser( $user );
		}

		$context->getOutput()->setRevisionId( $revisionId );

		$sidebar = [];
		SWSHooks::onSidebarBeforeOutput( $context->getSkin(), $sidebar );

		return $sidebar;
	}

	private function findLinkById( array $links, string $id ): ?array {
		foreach ( $links as $link ) {
			if ( ( $link['id'] ?? null ) === $id ) {
				return $link;
			}
		}

		return null;
	}

}
