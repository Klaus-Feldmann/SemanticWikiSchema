<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\ReplaceSubject;

use InvalidArgumentException;
use ProfessionalWiki\SWS\Application\DependentSubjectBuilder;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Application\SubjectResponseItemFactory;
use ProfessionalWiki\SWS\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\SWS\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Application\StatementListBuilder;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectEditNotAuthorizedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectNotFoundException;
use ProfessionalWiki\SWS\Application\SubjectHostingPageResolver;
use ProfessionalWiki\SWS\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\SWS\Application\SubjectRepository;
use ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Validation\ViolationDiff;

readonly class ReplaceSubjectAction {

	public function __construct(
		private SubjectRepository $subjectRepository,
		private SubjectHostingPageResolver $hostingPageResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private DependentSubjectBuilder $dependentSubjectBuilder,
		private DependentSubjectValidator $dependentSubjectValidator,
		private SubjectResponseItemFactory $responseItemFactory,
		private ReplaceSubjectPresenter $presenter,
		private bool $validationEnforced,
	) {
	}

	/**
	 * Full replacement: a null label clears the stored one, exactly as an omitted property name
	 * deletes its Statement.
	 *
	 * @param array<string, mixed> $statements
	 *
	 * @throws InvalidArgumentException When the request holds a value or a Dependent Subject that cannot be built.
	 * @throws SubjectIdInUseException When a Dependent Subject takes an id another Subject holds.
	 * @throws SubjectNotFoundException
	 * @throws SubjectEditNotAuthorizedException
	 */
	public function replace(
		SubjectId $subjectId,
		?string $label,
		array $statements,
		?string $comment,
		array $dependentSubjects = []
	): void {
		$pageIdentifiers = $this->hostingPageResolver->resolveReadableHostingPage( $subjectId );

		if ( $pageIdentifiers === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		if ( !$this->writeAuthorizer->authorize( $pageIdentifiers->getId() ) ) {
			throw new SubjectEditNotAuthorizedException();
		}

		$subject = $this->subjectRepository->getSubject( $subjectId );

		if ( $subject === null ) {
			throw SubjectNotFoundException::forId( $subjectId );
		}

		$pageSubjects = $dependentSubjects === [] ?
			PageSubjects::newEmpty() : $this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() );
		$newDependentSubjects = $this->dependentSubjectBuilder->build( $dependentSubjects, $subjectId, $pageSubjects );

		$priorViolations = $this->proposedSubjectValidator->validate( $subject );

		$subject->setLabel( SubjectLabel::fromText( $label ) );
		$subject->setStatements( $this->statementListBuilder->build( $statements ) );

		$proposedViolations = $this->proposedSubjectValidator->validate( $subject, $newDependentSubjects );
		$dependentSubjectViolations = $this->dependentSubjectValidator->validate(
			$newDependentSubjects,
			$subject,
			$pageSubjects->getAllSubjects()->onlyWithIds( $newDependentSubjects->getIds() )
		);
		$allViolations = array_merge( $proposedViolations, $dependentSubjectViolations->all );

		// Only violations this edit introduces block it, so a Subject that already carries one stays
		// editable rather than being frozen by it.
		if ( $this->validationEnforced
			&& $dependentSubjectViolations->blockTheWriteWith( ViolationDiff::newViolations( $proposedViolations, $priorViolations ) )
		) {
			$this->presenter->presentValidationFailed( $allViolations );
			return;
		}

		try {
			$status = $this->subjectRepository->updateSubject( $subject, $comment, $newDependentSubjects );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $allViolations, $exception->violations ) );
			return;
		}

		if ( $status->status === PageContentSavingStatus::ERROR ) {
			$this->presenter->presentSaveFailed( $status->errorMessage );
			return;
		}

		// The mutated Subject is the persisted state: the builder above already normalized what the
		// request supplied.
		$this->presenter->presentUpdated(
			$this->newResponseItem( $subject, $pageIdentifiers ),
			$this->schemaResolver->getSchema( $subject->getSchemaReference() ),
			$proposedViolations
		);
	}

	/**
	 * Which Subject the page treats as its own topic decides what a Subject without a label is called.
	 * Only the page knows that. A replace cannot change it, so reading it after the write is safe.
	 */
	private function newResponseItem( Subject $subject, PageIdentifiers $pageIdentifiers ): GetSubjectResponseItem {
		$pageSubjects = $this->subjectRepository->getSubjectsByPageId( $pageIdentifiers->getId() );

		return $this->responseItemFactory->createResponseItem( $subject, $pageIdentifiers, $pageSubjects );
	}

}
