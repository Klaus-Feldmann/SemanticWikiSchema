<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jClientReadQueryEngine
 * @group Database
 */
class Neo4jClientReadQueryEngineIntegrationTest extends SWSIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReadQueryReturnsNothingWhenDbIsEmpty(): void {
		$result = $this->readGraph( 'MATCH (n) RETURN n' );

		$this->assertSame( [], $result->toArray() );
		$this->assertTrue( $result->isEmpty() );
	}

}
