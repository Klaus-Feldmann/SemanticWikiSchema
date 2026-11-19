<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Application\WikiConfig\WikiConfigSource;

class StubWikiConfigSource implements WikiConfigSource {

	public int $readCount = 0;

	public function __construct(
		private readonly ?array $configData
	) {
	}

	public function readConfig(): ?array {
		$this->readCount++;
		return $this->configData;
	}

}
