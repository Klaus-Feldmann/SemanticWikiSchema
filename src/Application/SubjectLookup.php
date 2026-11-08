<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application;

use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;

interface SubjectLookup {

	public function getSubject( SubjectId $subjectId ): ?Subject;

	/**
	 * Ids that resolve to no Subject are absent from the map, so its size is the number found.
	 */
	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap;

}
