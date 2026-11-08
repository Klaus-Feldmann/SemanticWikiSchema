<?php

namespace ProfessionalWiki\SWS\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialMappings;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialMappings
 */
class SpecialMappingsTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialMappings {
		return new SpecialMappings();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-sws-mappings"',
			$output
		);
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( $this->newSpecialPage() ) );
	}

}
