<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Queries\GetSubjectEditNotices;

use AEWI\SWS\Domain\EditNotice\SubjectEditNotice;

interface GetSubjectEditNoticesPresenter {

	/**
	 * @param SubjectEditNotice[] $notices
	 */
	public function presentNotices( array $notices ): void;

}
