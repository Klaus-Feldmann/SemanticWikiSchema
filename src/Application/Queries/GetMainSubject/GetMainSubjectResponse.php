<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Queries\GetMainSubject;

use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;

readonly class GetMainSubjectResponse {

	public function __construct(
		public int $pageId,
		public ?GetSubjectResponseItem $subject,
	) {
	}

}
