<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNotice;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeContext;
use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNoticeProvider;

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
