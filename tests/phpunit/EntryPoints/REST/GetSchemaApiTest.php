<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\EntryPoints\REST\GetSchemaApi;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\REST\GetSchemaApi
 * @group Database
 */
class GetSchemaApiTest extends SWSIntegrationTestCase {
	use HandlerTestTrait;

	private SchemaName $schemaName;
	private const SCHEMA_ID = 'TestSchema';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA_ID, <<<JSON
{
	"title": "TestSchema",
	"description": "This is a test schema",
	"propertyDefinitions": {
		"MyRelation": {
			"type": "relation",
			"targetSchemas": [ "TestSchema" ],
			"description": "This is a test schema"
		}
	}
}
JSON
		);
	}

	public function testSchemaIsFound(): void {
		$response = $this->executeHandler(
			new GetSchemaApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'schemaName' => self::SCHEMA_ID
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "schema": {
        "description": "This is a test schema",
        "propertyDefinitions": {
            "MyRelation": {
                "description": "This is a test schema",
                "required": false,
                "default": null,
                "multiple": false,
                "type": "relation",
                "targetSchemas": [ "TestSchema" ]
            }
        }
    }
}
JSON,
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

	public function testSchemaIsNotFound(): void {
		$response = $this->executeHandler(
			new GetSchemaApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [
					'schemaName' => 'NonExistentSchema'
				]
			] )
		);

		$this->assertJsonStringEqualsJsonString(
			'{"schema":null}',
			$response->getBody()->getContents()
		);
		$this->assertSame( 200, $response->getStatusCode() );
	}

}
