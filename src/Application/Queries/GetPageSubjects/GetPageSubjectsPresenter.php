<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetPageSubjects;

interface GetPageSubjectsPresenter {

	public function presentPageSubjects( GetPageSubjectsResponse $response ): void;

}
