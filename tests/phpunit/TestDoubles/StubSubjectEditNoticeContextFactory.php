<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Application\EditNotice\SubjectEditNoticeContextFactory;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use AEWI\SWS\Domain\Page\PageId;

class StubSubjectEditNoticeContextFactory implements SubjectEditNoticeContextFactory {

	public function __construct(
		private readonly bool $pageExists
	) {
	}

	public function newContext( PageId $pageId, ?string $schemaName ): ?SubjectEditNoticeContext {
		if ( !$this->pageExists ) {
			return null;
		}

		return new SubjectEditNoticeContext(
			pageId: $pageId,
			pageDbKey: 'Berlin',
			namespaceId: 0,
			namespaceHasSubpages: false,
			schemaName: $schemaName
		);
	}

}
