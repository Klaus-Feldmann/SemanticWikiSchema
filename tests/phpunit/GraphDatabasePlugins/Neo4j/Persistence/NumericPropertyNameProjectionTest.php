<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\GraphDatabasePlugins\Neo4j\Persistence;

use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\SubjectLabel;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * A property named like a decimal integer is an int array key by the time the node property map is
 * assembled, so combining it with the map's fixed entries must not renumber it onto another name.
 *
 * @covers \AEWI\SWS\GraphDatabasePlugins\Neo4j\Persistence\Neo4jSubjectUpdater
 * @group Database
 */
class NumericPropertyNameProjectionTest extends SWSIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( 'NumericPropertyNameSchema' );
	}

	public function testPropertyNamedLikeAnIntegerKeepsItsNameInTheGraph(): void {
		$this->createPageWithSubjectNamedProperty();

		$this->assertSame(
			[ 'yes' ],
			$this->readSubjectProperty( '2024' )
		);
	}

	public function testPropertyNamedLikeAnIntegerDoesNotDisplaceTheFixedNodeProperties(): void {
		$this->createPageWithSubjectNamedProperty();

		$this->assertSame( 'Numbered subject', $this->readSubjectProperty( 'name' ) );
	}

	private function createPageWithSubjectNamedProperty(): void {
		$this->createPageWithSubjects(
			'NumericPropertyNameTest',
			mainSubject: TestSubject::build(
				id: 'sTestNP11111111',
				label: new SubjectLabel( 'Numbered subject' ),
				schemaName: new SchemaName( 'NumericPropertyNameSchema' ),
				statements: new StatementList( [
					TestStatement::build( property: '2024', value: 'yes', propertyType: 'text' ),
				] ),
			)
		);
	}

	private function readSubjectProperty( string $propertyName ): mixed {
		$result = $this->readGraph(
			'MATCH (subject:Subject {id: $id}) RETURN subject[$property] AS value',
			[ 'id' => 'sTestNP11111111', 'property' => $propertyName ]
		);

		return $result->first()->toRecursiveArray()['value'];
	}

}
