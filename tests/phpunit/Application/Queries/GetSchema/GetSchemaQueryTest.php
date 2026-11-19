<?php

declare( strict_types=1 );

namespace AEWI\SWS\Tests\Application\Queries\GetSchema;

use PHPUnit\Framework\TestCase;
use AEWI\SWS\Application\Queries\GetSchema\GetSchemaQuery;
use AEWI\SWS\Domain\Schema\PropertyDefinitions;
use AEWI\SWS\Domain\Schema\SchemaName;
use AEWI\SWS\Presentation\SchemaPresentationSerializer;
use AEWI\SWS\Tests\Data\TestProperty;
use AEWI\SWS\Tests\Data\TestSchema;
use AEWI\SWS\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \AEWI\SWS\Application\Queries\GetSchema\GetSchemaQuery
 */
class GetSchemaQueryTest extends TestCase {

	private InMemorySchemaLookup $schemaLookup;
	private GetSchemaPresenterSpy $presenter;

	protected function setUp(): void {
		$this->schemaLookup = new InMemorySchemaLookup();
		$this->presenter = new GetSchemaPresenterSpy();
	}

	public function testExecuteWhenSchemaNotFound(): void {
		$this->newGetSchemaQuery()->execute( 'doesNotExist' );

		$this->assertTrue( $this->presenter->notFound );
	}

	private function newGetSchemaQuery(): GetSchemaQuery {
		return new GetSchemaQuery(
			$this->presenter,
			$this->schemaLookup,
			new SchemaPresentationSerializer()
		);
	}

	public function testExecuteWithCompleteSchema(): void {
		$schemaName = 'testName';

		$schema = TestSchema::build(
			name: new SchemaName( $schemaName ),
			description: 'test',
			properties: new PropertyDefinitions( [
				'property1' => TestProperty::buildText(
					description: 'A string property',
					required: false,
					default: null,
					multiple: false
				)
			] )
		);

		$this->schemaLookup->updateSchema( $schema );

		$this->newGetSchemaQuery()->execute( $schemaName );

		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
    "description": "test",
    "propertyDefinitions": {
        "property1": {
            "type": "text",
            "description": "A string property",
            "required": false,
            "default": null,
            "multiple": false,
            "uniqueItems": false,
            "minLength": null,
            "maxLength": null
        }
    }
}
JSON
			,
			$this->presenter->schemaJson
		);
	}

}
