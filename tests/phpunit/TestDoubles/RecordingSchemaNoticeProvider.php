<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeProvider;

/**
 * Records the context it was handed, so tests can assert what reached the extension point.
 */
class RecordingSchemaNoticeProvider implements SubjectEditNoticeProvider {

	public ?string $receivedSchemaName = null;

	public ?int $receivedPageId = null;

	public function getNotices( SubjectEditNoticeContext $context ): array {
		$this->receivedSchemaName = $context->schemaName;
		$this->receivedPageId = $context->pageId->id;

		return [];
	}

}
