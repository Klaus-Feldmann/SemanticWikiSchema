<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception;

class EmptyQueryException extends QueryException {

	public function errorType(): string {
		return 'emptyQuery';
	}

}
