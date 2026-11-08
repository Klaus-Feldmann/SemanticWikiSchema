<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetMainSubject;

use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;

readonly class GetMainSubjectResponse {

	public function __construct(
		public int $pageId,
		public ?GetSubjectResponseItem $subject,
	) {
	}

}
