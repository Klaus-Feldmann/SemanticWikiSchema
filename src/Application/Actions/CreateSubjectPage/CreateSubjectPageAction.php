<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\Actions\CreateSubjectPage;

use ProfessionalWiki\SWS\Application\DependentSubjectBuilder;
use ProfessionalWiki\SWS\Application\NewSubjectIdResolver;
use ProfessionalWiki\SWS\Application\Subject\Exception\DependentSubjectRulesViolatedException;
use ProfessionalWiki\SWS\Application\Subject\Exception\SubjectIdInUseException;
use ProfessionalWiki\SWS\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\SWS\Application\PageIdentifiersResolver;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Application\Source\SchemaResolver;
use ProfessionalWiki\SWS\Application\StatementListBuilder;
use ProfessionalWiki\SWS\Application\SubjectRepository;
use ProfessionalWiki\SWS\Application\SubjectWriteAuthorizer;
use ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReferenceParser;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectDisplayName;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PageContentSavingStatus;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * Creates a Subject together with the page it lives on, in one revision, for callers who have a
 * thing to describe rather than a page to describe it on.
 */
readonly class CreateSubjectPageAction {

	public function __construct(
		private CreateSubjectPagePresenter $presenter,
		private SubjectRepository $subjectRepository,
		private NewSubjectIdResolver $newSubjectIdResolver,
		private SubjectWriteAuthorizer $writeAuthorizer,
		private StatementListBuilder $statementListBuilder,
		private SchemaResolver $schemaResolver,
		private ProposedSubjectValidator $proposedSubjectValidator,
		private PageIdentifiersResolver $pageIdentifiersResolver,
		private SchemaReferenceParser $schemaReferenceParser,
		private DependentSubjectBuilder $dependentSubjectBuilder,
		private DependentSubjectValidator $dependentSubjectValidator,
		private bool $validationEnforced,
		private bool $subjectFirst,
		private int $subjectPageNamespace,
		private string $identityLanguage = 'en',
	) {
	}

	/**
	 * @throws InvalidArgumentException When the request holds a value or a Dependent Subject that cannot be built.
	 * @throws SubjectIdInUseException When a Dependent Subject takes an id another Subject holds.
	 */
	public function createSubjectPage( CreateSubjectPageRequest $request ): void {
		$schemaReference = $this->schemaReferenceParser->localName( $request->schemaName );
		$titleAsked = $this->titleAsked( $request->pageTitle );

		if ( $titleAsked !== null && $this->subjectFirst ) {
			throw new InvalidArgumentException(
				'pageTitle cannot be given on a subject-first wiki, which titles the page by the Subject ID'
			);
		}

		$pageTitleAsked = $titleAsked === null ?
			null : $this->pageIdentifiersResolver->getTitleInNamespace( $this->subjectPageNamespace, $titleAsked );

		if ( $titleAsked !== null && $pageTitleAsked === null ) {
			throw new InvalidPageTitleException( $titleAsked );
		}

		$subject = $this->buildSubject( $request, $schemaReference );
		$pageTitle = $pageTitleAsked ?? $this->pageTitleFor( $subject->getId(), $request->label );

		// Authorized before the page is looked for, so that a title the caller may not write
		// answers the same whether or not a page holds it, rather than reporting one they may
		// not touch.
		if ( !$this->writeAuthorizer->authorizeCreatePage( $pageTitle ) ) {
			throw new RuntimeException( 'You do not have the necessary permissions to create this page' );
		}

		// Before the title check: a caller whose create already landed holds both the id and the
		// title it took, and the id is the answer that tells them their Subject exists.
		if ( $request->id !== null && $this->newSubjectIdResolver->isInUse( $subject->getId() ) ) {
			$this->presenter->presentSubjectAlreadyExists();
			return;
		}

		if ( $this->pageIdentifiersResolver->getIdentifiersOfTitle( $pageTitle ) !== null ) {
			$this->presenter->presentPageTitleTaken( $pageTitle );
			return;
		}

		$dependentSubjects = $this->dependentSubjectBuilder->build( $request->dependentSubjects, $subject->getId(), PageSubjects::newEmpty() );

		$violations = $this->proposedSubjectValidator->validate( $subject, $dependentSubjects );
		$dependentSubjectViolations = $this->dependentSubjectValidator->validate( $dependentSubjects, $subject, new SubjectMap() );
		$allViolations = array_merge( $violations, $dependentSubjectViolations->all );

		if ( $this->validationEnforced && $dependentSubjectViolations->blockTheWriteWith( $violations ) ) {
			$this->presenter->presentValidationFailed( $allViolations );
			return;
		}

		$pageSubjects = new PageSubjects( $subject, $dependentSubjects );
		$pageId = $this->createPage( $pageTitle, $pageSubjects, $request->comment, $allViolations );

		if ( $pageId === null ) {
			return;
		}

		$page = new PageIdentifiers( id: $pageId, title: $pageTitle, namespaceId: $this->subjectPageNamespace );

		$this->presenter->presentCreated(
			GetSubjectResponseItem::fromSubject(
				$subject,
				$page,
				SubjectDisplayName::labelOrPageName( $subject, $pageSubjects, $pageTitle ),
				hostSubject: null
			),
			$page,
			$this->schemaResolver->getSchema( $schemaReference ),
			$violations
		);
	}

	/**
	 * Null when the write failed, which this has then presented.
	 *
	 * @param Violation[] $violations
	 */
	private function createPage( string $pageTitle, PageSubjects $pageSubjects, ?string $comment, array $violations ): ?PageId {
		try {
			$status = $this->subjectRepository->createPageWithSubjects( $pageTitle, $pageSubjects, $comment );
		} catch ( DependentSubjectRulesViolatedException $exception ) {
			$this->presenter->presentValidationFailed( array_merge( $violations, $exception->violations ) );
			return null;
		}

		if ( $status->pageId === null ) {
			$this->presentFailedWrite( $pageTitle, $status );
		}

		return $status->pageId;
	}

	/**
	 * The write refuses a page that exists, which is the check the one before it cannot make: that
	 * one reads a replica, so a page created in the meantime is invisible to it. Looking again is
	 * what tells that lost race from a write that failed for a reason of its own.
	 */
	private function presentFailedWrite( string $pageTitle, PageContentSavingStatus $status ): void {
		if ( $this->pageIdentifiersResolver->getIdentifiersOfTitle( $pageTitle ) !== null ) {
			$this->presenter->presentPageTitleTaken( $pageTitle );
			return;
		}

		$this->presenter->presentPageNotCreated( $pageTitle, $status->errorMessage );
	}

	/**
	 * The title the caller chose, or null where they chose none: nothing, empty and blank all
	 * leave the choice to the label.
	 */
	private function titleAsked( ?string $pageTitle ): ?string {
		$trimmed = trim( $pageTitle ?? '' );

		return $trimmed === '' ? null : $trimmed;
	}

	private function pageTitleFor( SubjectId $subjectId, ?string $label ): string {
		$fromLabel = $label === null || $this->subjectFirst ?
			null : $this->pageIdentifiersResolver->getTitleInNamespace( $this->subjectPageNamespace, $label );

		return $fromLabel ?? $this->idPageTitle( $subjectId );
	}

	private function idPageTitle( SubjectId $subjectId ): string {
		return $this->pageIdentifiersResolver->getTitleInNamespace( $this->subjectPageNamespace, $subjectId->text )
			?? throw new LogicException( "No page can be created in namespace $this->subjectPageNamespace" );
	}

	private function buildSubject( CreateSubjectPageRequest $request, SchemaReference $schemaReference ): Subject {
		$schemaReference = $this->schemaResolver->getSchema( $schemaReference )?->getReference() ?? $schemaReference;
		return \ProfessionalWiki\SWS\Application\Identity\IdentityMetadataWriter::initialize( new Subject(
			id: $this->newSubjectIdResolver->resolve( $request->id ),
			label: SubjectLabel::fromText( $request->label ),
			schema: $schemaReference,
			statements: $this->statementListBuilder->build( $request->statements ),
			fieldKeyVersion: $this->schemaResolver->getSchema( $schemaReference )?->getFieldKeyVersion() ?? 0,
            fieldNotes: \ProfessionalWiki\SWS\Domain\FieldNotes::forSchema( $request->fieldNotes, [], $request->fieldNotes === [] ? null : $this->schemaResolver->getSchema( $schemaReference ) ),
	), $this->identityLanguage, $request->identity );
	}

}
