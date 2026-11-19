<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application;

use MediaWiki\Title\Title;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\SWSIntegrationTestCase;
use AEWI\SWS\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use RuntimeException;

/**
 * Guards the split between the two write paths at the wiring level: the maintenance rebuild path must
 * project through the scoped store unwrapped, so a backend failure escapes the rebuilder to the
 * rebuild, which decides whether it costs one page or the whole run. Wiring the rebuild path with the
 * hook path's failure-isolating decorator instead would swallow the throw and report every page as
 * rebuilt.
 *
 * @covers \AEWI\SWS\SWSExtension
 * @covers \AEWI\SWS\Application\PageRebuilder
 * @group Database
 */
class RebuildPathPropagatesProjectionFailureTest extends SWSIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testRebuildPropagatesBackendFailureSoTheRebuildCanReportIt(): void {
		$this->createPageWithSubjects( 'Rebuild failure page', TestSubject::build() );

		$rebuilder = SWSExtension::getInstance()->newPageRebuilderFor( new ThrowingGraphDatabasePlugin() );

		$this->expectException( RuntimeException::class );
		$this->expectExceptionMessage( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE );

		$rebuilder->rebuild( Title::newFromText( 'Rebuild failure page' ) );
	}

}
