<?php

declare( strict_types=1 );

namespace AEWI\SWS\Application\Queries\GetLayout;

interface GetLayoutPresenter {

	public function presentLayout( string $json ): void;

	public function presentLayoutNotFound(): void;

}
