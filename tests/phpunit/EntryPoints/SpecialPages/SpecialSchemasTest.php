<?php

namespace ProfessionalWiki\SWS\Tests\EntryPoints\SpecialPages;

use ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialSchemas;
use SpecialPageTestBase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SpecialPages\SpecialSchemas
 */
class SpecialSchemasTest extends SpecialPageTestBase {

	use HelpLinkAssertions;

	protected function newSpecialPage(): SpecialSchemas {
		return new SpecialSchemas();
	}

	public function testOutputContainsMountPoint(): void {
		/** @var string $output */
		[ $output ] = $this->executeSpecialPage();

		$this->assertStringContainsString(
			'id="ext-sws-schemas"',
			$output
		);
	}

	public function testLoadsTheFrontendModule(): void {
		$this->assertContains( 'ext.sws', $this->outputOf( $this->newSpecialPage() )->getModules() );
	}

	public function testTheHelpLinkLeadsToTheDocs(): void {
		$this->assertHelpLinkLeadsToTheDocs( $this->outputOf( $this->newSpecialPage() ) );
	}

}
