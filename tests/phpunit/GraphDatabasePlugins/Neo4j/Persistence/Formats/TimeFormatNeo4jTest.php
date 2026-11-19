<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\GraphDatabasePlugins\Neo4j\Persistence\Formats;

use Laudis\Neo4j\Types\LocalTime;
use AEWI\SWS\Domain\Schema\PropertyName;
use AEWI\SWS\Domain\Statement;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Value\StringValue;
use AEWI\SWS\Domain\PropertyType\Types\TimeType;
use AEWI\SWS\Tests\Data\TestPage;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * @group Database
 */
class TimeFormatNeo4jTest extends SWSIntegrationTestCase {

	public function setUp(): void {
		self::markTestSkipped( 'Format not supported yet' );
		//$this->setUpNeo4j();
	}

	public function testStoresAsDates(): void {
		$store = $this->newProjectionStore();
		$subjectId = TestSubject::uniqueId();

		$store->savePage( TestPage::build(
			mainSubject: TestSubject::build(
				id: $subjectId,
				statements: new StatementList( [
					new Statement(
						property: new PropertyName( 'MyProperty' ),
						propertyType: TimeType::NAME,
						value: new StringValue(
							'13:37',
							'Ignored bad value',
							'21:42:42',
							'00:00:61', // Invalid
							'24:00:01', // Invalid
						)
					),
				] )
			),
		) );

		$result = $this->readGraph(
			"MATCH (n {id: '$subjectId'}) RETURN n.MyProperty"
		)->toRecursiveArray()[0];

		$this->assertEquals(
			[
				new LocalTime( 49020000000000 ),
				new LocalTime( 78162000000000 ),
			],
			$result['n.MyProperty']
		);
	}

}
