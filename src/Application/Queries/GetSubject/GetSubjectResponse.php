<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSubject;

readonly class GetSubjectResponse {

	public function __construct(
		public string $requestedId,
		/**
		 * @var array<string, GetSubjectResponseItem> Indexed by subject ID
		 */
		public array $subjects,
	) {
	}

}
