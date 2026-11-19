<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Validation;

use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\Domain\Subject\SubjectMap;
use AEWI\SWS\Domain\Validation\Violation;
use AEWI\SWS\Domain\Validation\ViolationDiff;

readonly class DependentSubjectValidator {

	public function __construct(
		private ProposedSubjectValidator $proposedSubjectValidator,
	) {
	}

	/**
	 * Each Violation names its Subject. The Subjects written together count as existing relation targets, and a
	 * Violation a stored Dependent Subject already has is not new.
	 *
	 * @param SubjectMap $storedSubjects The Dependent Subjects as their page holds them before the write.
	 */
	public function validate( SubjectMap $dependentSubjects, Subject $hostSubject, SubjectMap $storedSubjects ): DependentSubjectViolations {
		$violations = [];
		$newBlockingViolations = [];

		foreach ( $dependentSubjects->asArray() as $subject ) {
			$proposed = $this->validateOne( $subject, $dependentSubjects->without( $subject->id )->prepend( $hostSubject ) );
			$stored = $storedSubjects->getSubject( $subject->id );

			$violations = array_merge( $violations, $proposed );
			$newBlockingViolations = array_merge(
				$newBlockingViolations,
				array_filter(
					ViolationDiff::newViolations( $proposed, $stored === null ? [] : $this->validateOne( $stored, new SubjectMap() ) ),
					static fn ( Violation $violation ): bool => $violation->isBlocking()
				)
			);
		}

		return new DependentSubjectViolations( $violations, array_values( $newBlockingViolations ) );
	}

	/**
	 * @return Violation[]
	 */
	private function validateOne( Subject $subject, SubjectMap $writtenAlongside ): array {
		return array_map(
			static fn ( Violation $violation ): Violation => $violation->withSubjectId( $subject->id ),
			$this->proposedSubjectValidator->validate( $subject, $writtenAlongside )
		);
	}

}
