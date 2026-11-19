<?php

declare( strict_types=1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\GraphDatabasePlugins\Neo4j\Application\CypherQueryValidator;

class SpyCypherQueryValidator implements CypherQueryValidator {

	public int $callCount = 0;

	public function queryIsAllowed( string $cypher ): bool {
		$this->callCount++;
		return true;
	}

}
