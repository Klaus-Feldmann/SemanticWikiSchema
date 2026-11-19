<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Domain\EditNotice\SubjectEditNotice;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use AEWI\SWS\Domain\EditNotice\SubjectEditNoticeProvider;

class StubSubjectEditNoticeProvider implements SubjectEditNoticeProvider {

	/**
	 * @param SubjectEditNotice[] $notices
	 */
	public function __construct(
		private readonly array $notices
	) {
	}

	public function getNotices( SubjectEditNoticeContext $context ): array {
		return $this->notices;
	}

}
