<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Actions;

use ProfessionalWiki\SWS\Application\Actions\ReplaceSubject\ReplaceSubjectPresenter;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Validation\Violation;

class ReplaceSubjectPresenterSpy implements ReplaceSubjectPresenter {

	public ?GetSubjectResponseItem $subject = null;

	public ?Schema $schema = null;

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public ?string $saveFailure = null;

	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->subject = $subject;
		$this->schema = $schema;
		$this->violations = $violations;
	}

	public function presentValidationFailed( array $violations ): void {
		$this->validationFailed = true;
		$this->violations = $violations;
	}

	public function presentSaveFailed( ?string $errorMessage ): void {
		$this->saveFailure = $errorMessage ?? '';
	}

}
