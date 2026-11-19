<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Neo4j\Application\Exception;

use RuntimeException;

abstract class QueryException extends RuntimeException {

	abstract public function errorType(): string;

}
