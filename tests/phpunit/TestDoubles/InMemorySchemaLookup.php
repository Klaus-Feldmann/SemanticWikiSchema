<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;

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
