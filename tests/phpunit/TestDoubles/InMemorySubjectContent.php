<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\TestDoubles;

use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;

class InMemorySubjectContent extends SubjectContent {

	public function __construct( private readonly PageSubjects $pageSubjects ) {
		parent::__construct( '{}' );
	}

	public function getPageSubjects(): PageSubjects {
		return $this->pageSubjects;
	}

}
