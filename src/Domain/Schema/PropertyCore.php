<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Domain\Schema;

use ProfessionalWiki\SWS\Domain\Validation\Severity;

readonly class PropertyCore {

	/**
	 * A null default means there is no default.
	 *
	 * @param array<string, Severity> $constraintSeverities Per-Constraint severity, keyed by
	 *   the Constraint's JSON key (e.g. 'maximum', 'required'). See ADR 26.
	 */
	public function __construct(
		public string $description,
		public bool $required,
		public mixed $default,
		public array $constraintSeverities = [],
		public ?array $wikiLink = null,
		public ?ExternalProperty $externalProperty = null,
		public ?array $fieldNotes = null,
	) {
	}

}
