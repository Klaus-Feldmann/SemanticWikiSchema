<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Sparql\Application;

use MediaWikiIntegrationTestCase;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits;

/**
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Sparql\Application\SparqlQueryLimits
 * @group Database
 */
class SparqlQueryLimitsTest extends MediaWikiIntegrationTestCase {

	private function overrideTiers(): void {
		$this->overrideConfigValue( 'SWSQueryLimits', [
			'default'   => [ 'timeoutSeconds' => 5, 'maxRows' => 100 ],
			'expensive' => [ 'timeoutSeconds' => 99, 'maxRows' => 999 ],
		] );
	}

	public function testDefaultTierReadsTheDefaultTierConfig(): void {
		$this->overrideTiers();

		$this->assertSame( 5, SparqlQueryLimits::defaultTier()->timeoutSeconds );
	}

	public function testUserWithApiHighLimitsGetsTheExpensiveTierTimeout(): void {
		$this->overrideTiers();

		$limits = SparqlQueryLimits::forUser( $this->getTestUser( [ 'bot' ] )->getUser() );

		$this->assertSame( 99, $limits->timeoutSeconds );
	}

}
