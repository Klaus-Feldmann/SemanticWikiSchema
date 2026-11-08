<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Queries\GetSubject;

interface GetSubjectPresenter {

	public function presentSubject( GetSubjectResponse $response ): void;

	public function presentSubjectNotFound(): void;

}
