<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Application\SchemaLookup;
use AEWI\SWS\Domain\Schema\SchemaName;

class InMemorySchemaLookup implements SchemaLookup {

	/**
	 * @var array<string, ?Schema>
	 */
	private array $schemas = [];

	public function __construct( Schema ...$schemas ) {
		array_walk( $schemas, $this->updateSchema( ... ) );
	}

	public function getSchema( SchemaName $schemaName ): ?Schema {
		return $this->schemas[$schemaName->getText()] ?? null;
	}

	public function updateSchema( Schema $schema ): void {
		$this->schemas[$schema->getName()->getText()] = $schema;
	}

}
