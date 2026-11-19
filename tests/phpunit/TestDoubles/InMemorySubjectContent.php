<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\TestDoubles;

use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\EntryPoints\Content\SubjectContent;

class InMemorySubjectContent extends SubjectContent {

	public function __construct( private readonly PageSubjects $pageSubjects ) {
		parent::__construct( '{}' );
	}

	public function getPageSubjects(): PageSubjects {
		return $this->pageSubjects;
	}

}
