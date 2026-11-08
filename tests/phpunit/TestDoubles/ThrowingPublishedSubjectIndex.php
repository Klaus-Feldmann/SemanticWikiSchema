<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Persistence\PublishedSubjectIndex;
use RuntimeException;

/**
 * Stands in for an index write that hits a database fault.
 */
class ThrowingPublishedSubjectIndex implements PublishedSubjectIndex {

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
		throw new RuntimeException( 'index write failed' );
	}

	public function removePage( PageId $pageId ): void {
		throw new RuntimeException( 'index write failed' );
	}

}
