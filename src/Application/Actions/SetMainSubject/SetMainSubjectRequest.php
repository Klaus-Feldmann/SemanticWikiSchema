<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\SetMainSubject;

readonly class SetMainSubjectRequest {

	public function __construct(
		public int $pageId,
		public ?string $subjectId,
		public ?string $comment = null,
	) {
	}

}
