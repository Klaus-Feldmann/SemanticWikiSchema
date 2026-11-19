<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Application\Queries\ValidateSubjectUpdate;

use AEWI\SWS\Application\StatementListBuilder;
use AEWI\SWS\Application\Subject\Exception\SubjectNotFoundException;
use AEWI\SWS\Application\SubjectHostingPageResolver;
use AEWI\SWS\Application\SubjectRepository;
use AEWI\SWS\Application\Validation\ProposedSubjectValidator;
use AEWI\SWS\Domain\Subject\SubjectIdParser;
use AEWI\SWS\Domain\Validation\Violation;

readonly class ValidateSubjectUpdateQuery {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private StatementListBuilder $statementListBuilder,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectIdParser $subjectIdParser,
	) {
	}

	/**
	 * @param array<string, mixed> $statements
	 *
	 * @return Violation[]
	 *
	 * @throws \InvalidArgumentException when the subject id format is invalid, or a value does not fit its
	 *  property type.
	 * @throws SubjectNotFoundException when the subject does not exist or the caller may not read its page.
	 */
	public function validate( string $subjectId, array $statements ): array {
		$id = $this->subjectIdParser->parseOrThrow( $subjectId );

		if ( $this->hostingPageResolver->resolveReadableHostingPage( $id ) === null ) {
			throw SubjectNotFoundException::forId( $id );
		}

		$subject = $this->subjectRepository->getSubject( $id );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $id );
		}

		return $this->proposedSubjectValidator->validate(
			$subject->withStatements( $this->statementListBuilder->build( $statements ) )
		);
	}

}
