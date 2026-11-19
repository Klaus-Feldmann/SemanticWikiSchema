<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Queries\GetPageSubjects;

interface GetPageSubjectsPresenter {

	public function presentPageSubjects( GetPageSubjectsResponse $response ): void;

}
