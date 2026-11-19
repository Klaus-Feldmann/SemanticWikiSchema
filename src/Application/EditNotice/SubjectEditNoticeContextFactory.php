<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\EditNotice;

use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use AEWI\SWS\Domain\Page\PageId;

/**
 * Resolves what a page id identifies into the shape edit notice providers are given. Separate from
 * {@see \AEWI\SWS\Application\PageIdentifiersResolver} because notices are keyed by
 * the database key, while that resolver answers with the prefixed title the graph projection stores.
 */
interface SubjectEditNoticeContextFactory {

	/**
	 * Null when no page carries this id.
	 */
	public function newContext( PageId $pageId, ?string $schemaName ): ?SubjectEditNoticeContext;

}
