<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Search;

/**
 * One searchable string of a Subject and the property it came from.
 */
readonly class SubjectSearchLine {

	/**
	 * @param ?string $propertyName Null for identity text (label or description)
	 */
	public function __construct(
		public ?string $propertyName,
		public string $text,
		public bool $isDescription = false
	) {
	}

}
