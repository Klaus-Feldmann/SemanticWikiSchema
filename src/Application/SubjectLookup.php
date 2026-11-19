<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application;

use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectId;
use AEWI\SWS\Domain\Subject\SubjectIdList;
use AEWI\SWS\Domain\Subject\SubjectMap;

interface SubjectLookup {

	public function getSubject( SubjectId $subjectId ): ?Subject;

	/**
	 * Ids that resolve to no Subject are absent from the map, so its size is the number found.
	 */
	public function getSubjects( SubjectIdList $subjectIds ): SubjectMap;

}
