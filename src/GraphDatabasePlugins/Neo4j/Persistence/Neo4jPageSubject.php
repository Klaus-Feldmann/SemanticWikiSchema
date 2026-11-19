<?php

declare( strict_types = 1 );

namespace AEWI\SWS\GraphDatabasePlugins\Neo4j\Persistence;

use AEWI\SWS\Domain\Subject\Subject;

/**
 * A Subject of a page being written to the graph, and whether it is the main Subject of that page.
 */
readonly class Neo4jPageSubject {

	public function __construct(
		public Subject $subject,
		public bool $isMainSubject,
		public bool $isDependent,
	) {
	}

	public function getId(): string {
		return $this->subject->id->text;
	}

}
