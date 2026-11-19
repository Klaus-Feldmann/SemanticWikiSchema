<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Application\Rdf\PageProjector;
use AEWI\SWS\Domain\Page\Page;
use AEWI\SWS\Domain\Rdf\QuadList;
use AEWI\SWS\Domain\Subject\SubjectId;

/**
 * A {@see PageProjector} that returns a preset {@see QuadList} regardless of the page, so tests can
 * exercise the store's update building and serialization against known quads.
 */
readonly class FixedPageProjector implements PageProjector {

	public function __construct(
		private QuadList $quads,
	) {
	}

	public function projectPage( Page $page ): QuadList {
		return $this->quads;
	}

	public function projectSubject( Page $page, SubjectId $subjectId ): QuadList {
		return $this->quads;
	}

}
