<?php

declare( strict_types=1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Infrastructure\IdGenerator;

class StubIdGenerator implements IdGenerator {

	public function __construct(
		private readonly string $id
	) {
	}

	public function generate(): string {
		return $this->id;
	}

}
