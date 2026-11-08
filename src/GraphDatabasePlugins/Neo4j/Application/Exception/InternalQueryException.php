<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception;

class InternalQueryException extends QueryException {

	public function errorType(): string {
		return 'internalError';
	}

}
