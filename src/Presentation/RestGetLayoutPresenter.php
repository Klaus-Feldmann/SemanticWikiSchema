<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Presentation;

use AEWI\SWS\Application\Queries\GetLayout\GetLayoutPresenter;

class RestGetLayoutPresenter implements GetLayoutPresenter {

	private string $apiResponse = '';

	public function getJson(): string {
		return $this->apiResponse;
	}

	public function presentLayout( string $json ): void {
		$this->apiResponse = (string)json_encode(
			[
				'layout' => json_decode( $json ),
			],
			JSON_PRETTY_PRINT
		);
	}

	public function presentLayoutNotFound(): void {
		$this->apiResponse = '{"layout":null}';
	}

}
