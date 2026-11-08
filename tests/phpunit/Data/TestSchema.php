<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Data;

use ProfessionalWiki\SWS\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;

class TestSchema {

	public static function build(
		string|SchemaName $name = 'TestSchemaName',
		string $description = 'Test Schema Description',
		PropertyDefinitions $properties = new PropertyDefinitions( [] ),
		bool $subjectsAreDependent = false,
	): Schema {
		return new Schema(
			name: $name instanceof SchemaName ? $name : new SchemaName( $name ),
			description: $description,
			properties: $properties,
			subjectsAreDependent: $subjectsAreDependent,
		);
	}

	public static function reference( SchemaName|SchemaReference|string $schema ): SchemaReference {
		if ( $schema instanceof SchemaReference ) {
			return $schema;
		}

		return SchemaReference::local( $schema instanceof SchemaName ? $schema : new SchemaName( $schema ) );
	}

}
