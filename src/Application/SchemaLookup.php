<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;

interface SchemaLookup {

	public function getSchema( SchemaName $schemaName ): ?Schema;

}
