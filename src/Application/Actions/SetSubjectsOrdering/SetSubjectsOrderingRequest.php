<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\SetSubjectsOrdering;

readonly class SetSubjectsOrderingRequest {

	/**
	 * @param string[] $otherSubjectIds
	 */
	public function __construct(
		public int $pageId,
		public ?string $mainSubjectId,
		public array $otherSubjectIds,
		public ?string $comment = null,
	) {
	}

}
