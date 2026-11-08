<?php

declare( strict_types=1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSchema;

interface GetSchemaPresenter {

	public function presentSchema( string $json ): void;

	public function presentSchemaNotFound(): void;

}
