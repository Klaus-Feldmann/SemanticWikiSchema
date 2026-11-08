<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\SpecialPage\SpecialPageFactory;
use MediaWiki\Specials\SpecialSpecialPages;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onSpecialPageInitList
 * @group Database
 */
class SpecialPageGroupOrderTest extends SpecialPageTestBase {

	protected function newSpecialPage(): SpecialSpecialPages {
		return new SpecialSpecialPages();
	}

	public function testSWSGroupIsListedFirst(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertSame(
			'sws',
			$this->firstGroupHeadingIn( $output ),
			'Without the reordering "maintenance" leads, as Special:BrokenRedirects heads the core list'
		);
	}

	public function testSpecialPageListStartsWithASWSPage(): void {
		$factory = $this->specialPageFactory();
		$firstPage = $factory->getNames()[0];

		$this->assertSame(
			'sws',
			$factory->getPage( $firstPage )?->getFinalGroupName(),
			"$firstPage heads the special page list, and it is not a SWS page"
		);
	}

	public function testReorderingKeepsTheCoreSpecialPages(): void {
		$this->assertContains(
			'Recentchanges',
			$this->specialPageFactory()->getNames(),
			'Moving the SWS pages to the front must not drop the pages they overtake'
		);
	}

	private function specialPageFactory(): SpecialPageFactory {
		return $this->getServiceContainer()->getSpecialPageFactory();
	}

	/**
	 * The group of the first "(specialpages-group-<group>)" message in the qqx output.
	 */
	private function firstGroupHeadingIn( string $html ): ?string {
		preg_match( '/\(specialpages-group-([a-z0-9-]+)\)/', $html, $matches );

		return $matches[1] ?? null;
	}

}
