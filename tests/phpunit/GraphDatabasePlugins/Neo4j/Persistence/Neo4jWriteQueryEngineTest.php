<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \AEWI\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jWriteQueryEngine
 * @group Database
 */
class Neo4jWriteQueryEngineTest extends SWSIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testRunWriteQuerySavesToDb(): void {
		$this->writeGraph( 'CREATE (:TestNode {name: "Test"} )' );

		$result = $this->readGraph( 'MATCH (node:TestNode {name: "Test"}) RETURN node.name' );

		$this->assertSame(
			[
				[ 'node.name' => 'Test' ]
			],
			$result->toRecursiveArray()
		);
	}

}
