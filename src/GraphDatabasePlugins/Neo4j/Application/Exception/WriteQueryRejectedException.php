<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Neo4j\Application\Exception;

class WriteQueryRejectedException extends QueryException {

	public function errorType(): string {
		return 'writeQueryRejected';
	}

}
