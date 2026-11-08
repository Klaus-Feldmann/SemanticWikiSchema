<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSubjectEditNotices;

use ProfessionalWiki\SWS\Domain\EditNotice\SubjectEditNotice;

interface GetSubjectEditNoticesPresenter {

	/**
	 * @param SubjectEditNotice[] $notices
	 */
	public function presentNotices( array $notices ): void;

}
