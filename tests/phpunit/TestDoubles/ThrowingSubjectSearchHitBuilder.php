<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Application\Search\SearchTermMatcher;
use AEWI\SWS\Application\Search\SubjectSearchHit;
use AEWI\SWS\Application\Search\SubjectSearchHitBuilder;
use AEWI\SWS\Domain\Page\PageSubjects;
use RuntimeException;

/**
 * A builder that fails, as a Source or Schema lookup below a result row can.
 */
readonly class ThrowingSubjectSearchHitBuilder extends SubjectSearchHitBuilder {

	public const string MESSAGE = 'The Source could not be reached';

	/**
	 * The parent's text builder is left unset: build() throws before anything can read it.
	 */
	public function __construct() {
	}

	public function build(
		PageSubjects $pageSubjects,
		string $pageName,
		bool $pageHasContent,
		?SearchTermMatcher $matcher
	): ?SubjectSearchHit {
		throw new RuntimeException( self::MESSAGE );
	}

}
