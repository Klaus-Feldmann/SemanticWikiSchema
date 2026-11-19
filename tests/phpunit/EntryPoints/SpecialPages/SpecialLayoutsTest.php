<?php

namespace AEWI\SWS\Tests\EntryPoints\SpecialPages;

use AEWI\SWS\EntryPoints\SpecialPages\SpecialLayouts;
use SpecialPageTestBase;

/**
 * @covers \AEWI\SWS\EntryPoints\SpecialPages\SpecialLayouts
 */
class SpecialLayoutsTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialLayouts {
		return new SpecialLayouts();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-sws-layouts"',
			$output
		);
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( $this->newSpecialPage() ) );
	}

}
