<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application;

use MediaWiki\Page\PageIdentity;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\EntryPoints\Content\SubjectContent;

interface SubjectContentRepository {

	public function getSubjectContentByPageId( PageId $pageId ): ?SubjectContent;

	public function getSubjectContentByPageTitle( PageIdentity $pageIdentity ): ?SubjectContent;

	public function getSubjectContentByRevisionId( int $revisionId ): ?SubjectContent;

	public function editSubjectContent(
		SubjectContent $subjectContent,
		PageId $pageId,
		string $editSummary
	): void;

}
