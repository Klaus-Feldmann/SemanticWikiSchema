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
		private SchemaPresentationSerializer $serializer,
        private ?\Closure $canonicalName = null,
	) {
	}

	public function execute( string $schemaName ): void {
		$name = $this->canonicalName === null ? new SchemaName( $schemaName ) : ( $this->canonicalName )( $schemaName );
        $schema = $this->schemaLookup->getSchema( $name );

		if ( $schema === null ) {
			$this->presenter->presentSchemaNotFound();
			return;
		}

		$this->presenter->presentSchema(
			$this->serializer->serialize( $schema )
		);
	}

}
