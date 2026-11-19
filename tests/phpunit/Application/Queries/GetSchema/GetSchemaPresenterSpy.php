<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Queries\GetSchema;

use AEWI\SWS\Application\Queries\GetSchema\GetSchemaPresenter;

class GetSchemaPresenterSpy implements GetSchemaPresenter {

	public string $schemaJson = '';
	public bool $notFound = false;

	public function presentSchema( string $json ): void {
		$this->schemaJson = $json;
	}

	public function presentSchemaNotFound(): void {
		$this->notFound = true;
	}

}
