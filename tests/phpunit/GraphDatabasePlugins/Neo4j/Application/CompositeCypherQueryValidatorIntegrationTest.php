<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Neo4j\Application;

use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\ExplainCypherQueryValidator;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\KeywordCypherQueryValidator;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\CompositeCypherQueryValidator
 * @group Database
 */
class CompositeCypherQueryValidatorIntegrationTest extends SWSIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	public function testReadOnlyQueryIsAllowed(): void {
		$validator = $this->newValidator();

		$this->assertTrue( $validator->queryIsAllowed( 'MATCH (n) RETURN n LIMIT 5' ) );
	}

	public function testWriteQueryIsRejected(): void {
		$validator = $this->newValidator();

		$this->assertFalse( $validator->queryIsAllowed( 'CREATE (n:Test {name: "test"}) RETURN n' ) );
	}

	public function testAdminQueryIsRejected(): void {
		$validator = $this->newValidator();

		$this->assertFalse( $validator->queryIsAllowed( 'RENAME USER nonexistent TO renamed' ) );
	}

	public function testAdminQueryPassesKeywordValidatorButIsRejectedByComposite(): void {
		$query = 'RENAME USER nonexistent TO renamed';

		$keywordValidator = new KeywordCypherQueryValidator();
		$this->assertTrue( $keywordValidator->queryIsAllowed( $query ) );

		$explainValidator = new ExplainCypherQueryValidator(
			SWSExtension::getInstance()->getReadOnlyNeo4jClient()
		);
		$this->assertFalse( $explainValidator->queryIsAllowed( $query ) );

		$compositeValidator = new CompositeCypherQueryValidator( [ $keywordValidator, $explainValidator ] );
		$this->assertFalse( $compositeValidator->queryIsAllowed( $query ) );
	}

	private function newValidator(): CompositeCypherQueryValidator {
		return new CompositeCypherQueryValidator( [
			new KeywordCypherQueryValidator(),
			new ExplainCypherQueryValidator(
				SWSExtension::getInstance()->getReadOnlyNeo4jClient()
			),
		] );
	}

}
