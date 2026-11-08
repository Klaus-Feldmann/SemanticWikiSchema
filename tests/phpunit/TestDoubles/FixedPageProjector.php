<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Application\Rdf\PageProjector;
use ProfessionalWiki\SWS\Domain\Page\Page;
use ProfessionalWiki\SWS\Domain\Rdf\QuadList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;

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
