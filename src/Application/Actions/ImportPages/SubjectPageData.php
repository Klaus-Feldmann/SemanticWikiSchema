<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\ImportPages;

class SubjectPageData {

	public function __construct(
		public readonly string $pageName,
		public readonly string $wikitext,
		public readonly string $subjectsJson
	) {
	}

}
