<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application;

use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Schema\SchemaName;

interface SchemaLookup {

	public function getSchema( SchemaName $schemaName ): ?Schema;

}
