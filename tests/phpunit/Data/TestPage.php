<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Data;

use ProfessionalWiki\SWS\Domain\Page\Page;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageProperties;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

/**
 * @see \ProfessionalWiki\SWS\Domain\Page\Page
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
