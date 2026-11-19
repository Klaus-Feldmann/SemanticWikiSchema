<?php

declare( strict_types=1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Infrastructure\GuidGenerator;

class StubGuidGenerator implements GuidGenerator {

	public function __construct(
		private readonly string $guid
	) {
	}

	public function generate(): string {
		return $this->guid;
	}

}
