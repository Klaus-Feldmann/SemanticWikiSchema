<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetMainSubject;

interface GetMainSubjectPresenter {

	public function presentMainSubject( GetMainSubjectResponse $response ): void;

}
