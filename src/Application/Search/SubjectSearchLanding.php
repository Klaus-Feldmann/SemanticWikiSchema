<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Search;

use AEWI\SWS\Domain\Subject\SubjectId;

/**
 * The Subject a result row leads to instead of its page.
 */
readonly class SubjectSearchLanding {

	/**
	 * @param ?string $chosenName Null when nobody chose a name, which leaves the row to name the
	 *   Subject by its id
	 */
	public function __construct(
		public SubjectId $subjectId,
		public ?string $chosenName
	) {
	}

}
