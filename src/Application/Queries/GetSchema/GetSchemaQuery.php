<?php

declare( strict_types=1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSchema;

use ProfessionalWiki\SWS\Application\SchemaLookup;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Presentation\SchemaPresentationSerializer;

readonly class GetSchemaQuery {

	public function __construct(
		private GetSchemaPresenter $presenter,
		private SchemaLookup $schemaLookup,
		private SchemaPresentationSerializer $serializer
	) {
	}

	public function execute( string $schemaName ): void {
		$schema = $this->schemaLookup->getSchema( new SchemaName( $schemaName ) );

		if ( $schema === null ) {
			$this->presenter->presentSchemaNotFound();
			return;
		}

		$this->presenter->presentSchema(
			$this->serializer->serialize( $schema )
		);
	}

}
