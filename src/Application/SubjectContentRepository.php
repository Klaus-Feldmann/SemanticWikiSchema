<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use MediaWiki\Page\PageIdentity;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;

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
