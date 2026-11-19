<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\Persistence\SubjectPageIndex;
use RuntimeException;

/**
 * Stands in for an index write that hits a database fault. Nothing isolates these, so they propagate
 * out of whatever called them.
 */
class ThrowingSubjectPageIndex implements SubjectPageIndex {

	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void {
		throw new RuntimeException( 'index write failed' );
	}

	public function removePage( PageId $pageId ): void {
		throw new RuntimeException( 'index write failed' );
	}

}
