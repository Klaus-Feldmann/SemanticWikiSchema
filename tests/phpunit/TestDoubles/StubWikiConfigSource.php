<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Application\WikiConfig\WikiConfigSource;

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
