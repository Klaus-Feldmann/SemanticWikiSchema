<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetReferencingSubjects;

interface GetReferencingSubjectsPresenter {

	public function presentReferencingSubjects( GetReferencingSubjectsResponse $response ): void;

}
