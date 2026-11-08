<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Actions;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Actions\SetMainSubject\SetMainSubjectAction;
use ProfessionalWiki\SWS\Application\Actions\SetMainSubject\SetMainSubjectPresenter;
use ProfessionalWiki\SWS\Application\Actions\SetMainSubject\SetMainSubjectRequest;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Validation\Severity;
use ProfessionalWiki\SWS\Domain\Validation\Violation;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\SWS\Tests\TestDoubles\SpySubjectWriteAuthorizer;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;
use ProfessionalWiki\SWS\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\SWS\Application\Actions\SetMainSubject\SetMainSubjectAction
 */
class SetMainSubjectActionTest extends TestCase {

	private const string MAIN_ID = 's11111111111maa';
	private const string OTHER_SUBJECT_ID = 's11111111111caa';
	private const string UNKNOWN_SUBJECT_ID = 's11111111111oaa';
	private const int PAGE_ID = 42;

	public function testPromotesOtherSubjectToMainAndDemotesPreviousMain(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID, comment: 'Promote other subject' )
		);

		$this->assertTrue( $presenter->changed );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::OTHER_SUBJECT_ID, $saved->getMainSubject()->id->text );
		$this->assertTrue( $saved->getOtherSubjects()->hasSubject( TestSubject::build( id: self::MAIN_ID )->id ) );
		$this->assertSame( 'Promote other subject', $repository->comments[self::PAGE_ID] );
	}

	public function testClearingMainDemotesPreviousMainToOtherSubject(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: null )
		);

		$this->assertTrue( $presenter->changed );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertNull( $saved->getMainSubject() );
		$this->assertSame( 2, $saved->getOtherSubjects()->count() );
		$this->assertTrue( $saved->getOtherSubjects()->hasSubject( TestSubject::build( id: self::MAIN_ID )->id ) );
		$this->assertTrue( $saved->getOtherSubjects()->hasSubject( TestSubject::build( id: self::OTHER_SUBJECT_ID )->id ) );
	}

	public function testClearingMainOnPageWithoutMainIsNoOp(): void {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build( id: self::OTHER_SUBJECT_ID ) ) ),
			new PageId( self::PAGE_ID )
		);
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: null )
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testPromotingTheCurrentMainIsNoOp(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::MAIN_ID )
		);

		$this->assertTrue( $presenter->noChange );
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testPromotingUnknownSubjectIdReportsNotFound(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();

		$presenter = $this->newSpyPresenter();

		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::UNKNOWN_SUBJECT_ID )
		);

		$this->assertTrue( $presenter->notFound );

		$saved = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );
		$this->assertSame( self::MAIN_ID, $saved->getMainSubject()->id->text );
	}

	public function testThrowsWhenUserMayReadButNotEditPage(): void {
		$action = new SetMainSubjectAction(
			presenter: $this->newSpyPresenter(),
			subjectRepository: new InMemorySubjectRepository(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
			subjectIdParser: TestSubjectIds::newParser(),
		);

		$this->expectException( \RuntimeException::class );
		$this->expectExceptionMessage( 'You do not have the necessary permissions to change the main subject' );

		$action->setMainSubject( new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID ) );
	}

	public function testReportsPageNotFoundWhenUserMayNotReadPage(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();
		$before = $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) );

		$presenter = $this->newSpyPresenter();

		( new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
			subjectIdParser: TestSubjectIds::newParser(),
		) )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
		// The page is left untouched: a denied read never reaches the write.
		$this->assertEquals( $before, $repository->getSubjectsByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReadDenialTakesPrecedenceOverWriteDenial(): void {
		// A page the caller can neither read nor edit answers not-found, never the write 403, so a
		// hidden page is indistinguishable from an absent one.
		$presenter = $this->newSpyPresenter();

		( new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $this->newRepositoryWithMainAndOtherSubject(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: false ),
			subjectIdParser: TestSubjectIds::newParser(),
		) )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
	}

	public function testReportsPageNotFoundWhenTheSaveFails(): void {
		// The page passed the read and write checks but is gone by the time the save runs: the
		// dropped write must be reported as not-found, never as changed.
		$repository = $this->newRepositoryWithMainAndOtherSubject();
		$repository->failNextSave = true;

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID )
		);

		$this->assertTrue( $presenter->pageNotFound );
		$this->assertFalse( $presenter->changed );
	}

	public function testPresentsAChangeTheRulesForDependentSubjectsRefuse(): void {
		$repository = $this->newRepositoryWithMainAndOtherSubject();
		$refusal = new Violation( null, 'dependent-subject-is-main', severity: Severity::Error, subjectId: new SubjectId( self::OTHER_SUBJECT_ID ) );
		$repository->refuseWritesWith = [ $refusal ];

		$presenter = $this->newSpyPresenter();
		$this->newAction( $presenter, $repository )->setMainSubject(
			new SetMainSubjectRequest( pageId: self::PAGE_ID, subjectId: self::OTHER_SUBJECT_ID )
		);

		$this->assertEquals( [ $refusal ], $presenter->violations );
	}

	private function newRepositoryWithMainAndOtherSubject(): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build( id: self::MAIN_ID ),
				new SubjectMap( TestSubject::build( id: self::OTHER_SUBJECT_ID ) )
			),
			new PageId( self::PAGE_ID )
		);
		return $repository;
	}

	private function newAction( SetMainSubjectPresenter $presenter, InMemorySubjectRepository $repository ): SetMainSubjectAction {
		return new SetMainSubjectAction(
			presenter: $presenter,
			subjectRepository: $repository,
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			writeAuthorizer: new SpySubjectWriteAuthorizer( allowed: true ),
			subjectIdParser: TestSubjectIds::newParser(),
		);
	}

	private function newSpyPresenter(): object {
		return new class() implements SetMainSubjectPresenter {

			public bool $changed = false;
			public bool $noChange = false;
			public bool $notFound = false;
			public bool $pageNotFound = false;

			/** @var Violation[] */
			public array $violations = [];

			public function presentMainSubjectChanged(): void {
				$this->changed = true;
			}

			public function presentNoChange(): void {
				$this->noChange = true;
			}

			public function presentSubjectNotFound(): void {
				$this->notFound = true;
			}

			public function presentPageNotFound(): void {
				$this->pageNotFound = true;
			}

			public function presentValidationFailed( array $violations ): void {
				$this->violations = $violations;
			}

		};
	}

}
