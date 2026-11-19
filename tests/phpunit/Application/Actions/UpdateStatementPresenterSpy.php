<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Application\Actions;

use AEWI\SWS\Application\Actions\UpdateStatement\UpdateStatementPresenter;
use AEWI\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use AEWI\SWS\Domain\Schema\Schema;
use AEWI\SWS\Domain\Validation\Violation;

class UpdateStatementPresenterSpy implements UpdateStatementPresenter {

	public string $subjectId = '';

	public ?GetSubjectResponseItem $subject = null;

	public ?Schema $schema = null;

	/** @var Violation[] */
	public array $violations = [];

	public bool $validationFailed = false;

	public ?string $saveFailure = null;

	public function presentUpdated( GetSubjectResponseItem $subject, ?Schema $schema, array $violations ): void {
		$this->subjectId = $subject->id;
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
