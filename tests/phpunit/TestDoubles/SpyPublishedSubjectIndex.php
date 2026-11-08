<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Persistence\PublishedSubjectIndex;

class SpyPublishedSubjectIndex implements PublishedSubjectIndex {

	/**
	 * @var array<int, string[]> The ids of the Subjects each page was indexed with, keyed by page id
	 */
	public array $indexedSubjectsByPageId = [];

	/**
	 * @var int[]
	 */
	public array $removedPageIds = [];

	public function setSubjectsOfPage(
		PageId $pageId,
		string $pageName,
		PageSubjects $subjects,
		string $publishedTimestamp
	): void {
		$this->indexedSubjectsByPageId[$pageId->id] = $subjects->getAllSubjects()->getIdsAsTextArray();
	}

	public function removePage( PageId $pageId ): void {
		$this->removedPageIds[] = $pageId->id;
	}

}
