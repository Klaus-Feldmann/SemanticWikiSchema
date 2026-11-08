<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Validation;

use ProfessionalWiki\SWS\Domain\Validation\Violation;

readonly class DependentSubjectViolations {

	/**
	 * @param Violation[] $all
	 * @param Violation[] $newBlocking The blocking ones among $all that the write introduces.
	 */
	public function __construct(
		public array $all,
		public array $newBlocking,
	) {
	}

	/**
	 * @param Violation[] $newSubjectViolations Those the write introduces on the Subject it creates or replaces.
	 */
	public function blockTheWriteWith( array $newSubjectViolations ): bool {
		return $this->newBlocking !== []
			|| array_filter( $newSubjectViolations, static fn ( Violation $violation ): bool => $violation->isBlocking() ) !== [];
	}

}
