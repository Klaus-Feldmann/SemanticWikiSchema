<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Persistence;

use ProfessionalWiki\SWS\Domain\Page\PageId;

/**
 * The write side of the subject -> page index that {@see \ProfessionalWiki\SWS\Application\PageIdentifiersLookup}
 * reads (ADR 32).
 */
interface SubjectPageIndex {

	/**
	 * Records that the page holds exactly these Subjects, dropping whatever it held before. Idempotent,
	 * so the same page can be indexed as often as it is written.
	 *
	 * @param string[] $subjectIds Without duplicates: a page holds each of its Subjects once.
	 */
	public function setSubjectsOfPage( PageId $pageId, array $subjectIds ): void;

	public function removePage( PageId $pageId ): void;

}
