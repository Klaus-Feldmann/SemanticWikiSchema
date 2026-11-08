<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Neo4j\Persistence\Formats;

use Laudis\Neo4j\Types\LocalTime;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\Domain\PropertyType\Types\TimeType;
use ProfessionalWiki\SWS\Tests\Data\TestPage;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

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
