<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;

/**
 * A page's Subjects in the revision the Subject being named was read from: the latest, the published one, or one a
 * read asked for.
 */
interface PageSubjectsAtRevisionLookup {

	/**
	 * Empty when that revision holds no Subjects.
	 */
	public function getPageSubjects( PageId $pageId ): PageSubjects;

}
