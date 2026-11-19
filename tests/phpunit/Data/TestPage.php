<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Data;

use AEWI\SWS\Domain\Page\Page;
use AEWI\SWS\Domain\Page\PageId;
use AEWI\SWS\Domain\Page\PageProperties;
use AEWI\SWS\Domain\Page\PageSubjects;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectMap;

/**
 * @see \AEWI\SWS\Domain\Page\Page
 */
class TestPage {

	public static function build(
		?int $id = null,
		?PageProperties $properties = null,
		?Subject $mainSubject = null,
		SubjectMap $otherSubjects = new SubjectMap()
	): Page {
		return new Page(
			id: new PageId( $id ?? 31337 ),
			properties: $properties ?? TestPageProperties::build(),
			subjects: new PageSubjects( $mainSubject, $otherSubjects )
		);
	}

}
