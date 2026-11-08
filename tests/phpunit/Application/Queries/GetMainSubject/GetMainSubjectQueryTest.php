<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Queries\GetMainSubject;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\PageIdentifiersResolver;
use ProfessionalWiki\SWS\Application\PageReadAuthorizer;
use ProfessionalWiki\SWS\Application\PageSubjectsLookup;
use ProfessionalWiki\SWS\Application\Queries\GetMainSubject\GetMainSubjectPresenter;
use ProfessionalWiki\SWS\Application\Queries\GetMainSubject\GetMainSubjectQuery;
use ProfessionalWiki\SWS\Application\Queries\GetMainSubject\GetMainSubjectResponse;
use ProfessionalWiki\SWS\Application\Queries\GetSubject\GetSubjectResponseItem;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersResolver;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectRepository;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;

/**
 * @covers \ProfessionalWiki\SWS\Application\Queries\GetMainSubject\GetMainSubjectQuery
 */
class GetMainSubjectQueryTest extends TestCase {

	private const PAGE_ID = 42;
	private const PAGE_TITLE = 'Berlin';
	private const MAIN_SUBJECT_ID = 's11111111111maa';
	private const OTHER_SUBJECT_ID = 's11111111111ca1';

	public function testMainSubjectIsServedWithItsPage(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: TestSubject::build(
				id: self::MAIN_SUBJECT_ID,
				label: new SubjectLabel( 'Berlin, Germany' ),
				schemaName: new SchemaName( 'City' ),
				statements: new StatementList( [
					TestStatement::build( 'population', '3700000' ),
				] )
			) )
		)->execute( self::PAGE_ID );

		$this->assertEquals(
			new GetMainSubjectResponse(
				pageId: self::PAGE_ID,
				subject: new GetSubjectResponseItem(
					id: self::MAIN_SUBJECT_ID,
					label: 'Berlin, Germany',
					displayName: 'Berlin, Germany',
					displayNameIsGenerated: false,
					schema: 'City',
					statements: [
						'population' => [
							'propertyType' => 'text',
							'value' => [ '3700000' ],
						],
					],
					pageId: self::PAGE_ID,
					pageTitle: self::PAGE_TITLE,
					pageNamespaceId: NS_MAIN,
					hostSubject: null,
				)
			),
			$presenter->response
		);
	}

	public function testLabellessMainSubjectIsNamedAfterItsPage(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: TestSubject::build( id: self::MAIN_SUBJECT_ID, label: null ) )
		)->execute( self::PAGE_ID );

		$this->assertSame( self::PAGE_TITLE, $presenter->response->subject->displayName );
		$this->assertFalse( $presenter->response->subject->displayNameIsGenerated );
	}

	/**
	 * A page titled by the id of a Subject it holds was titled by the system (ADR 31), so the page name
	 * names nothing and the Main Subject falls back to its Schema name. The title is the other Subject's
	 * id, so the rule has to see the whole page, not the Main Subject alone.
	 */
	public function testLabellessMainSubjectOfAPageTitledByASubjectIdIsNamedAfterItsSchema(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: TestSubject::build(
				id: self::MAIN_SUBJECT_ID,
				label: null,
				schemaName: new SchemaName( 'City' )
			) ),
			pageIdentifiersResolver: new InMemoryPageIdentifiersResolver( [
				new PageIdentifiers( id: new PageId( self::PAGE_ID ), title: 'S11111111111ca1', namespaceId: NS_MAIN ),
			] )
		)->execute( self::PAGE_ID );

		$this->assertSame( 'City', $presenter->response->subject->displayName );
		$this->assertTrue( $presenter->response->subject->displayNameIsGenerated );
	}

	public function testLabellessMainSubjectOfAPageInTheSubjectNamespaceTitledByASubjectIdIsNamedAfterItsSchema(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: TestSubject::build(
				id: self::MAIN_SUBJECT_ID,
				label: null,
				schemaName: new SchemaName( 'City' )
			) ),
			pageIdentifiersResolver: new InMemoryPageIdentifiersResolver( [
				new PageIdentifiers(
					id: new PageId( self::PAGE_ID ),
					title: 'Subject:' . ucfirst( self::MAIN_SUBJECT_ID ),
					namespaceId: SWSExtension::NS_SUBJECT
				),
			] )
		)->execute( self::PAGE_ID );

		$this->assertSame( 'City', $presenter->response->subject->displayName );
		$this->assertTrue( $presenter->response->subject->displayNameIsGenerated );
	}

	public function testPageWithOnlyOtherSubjectsHasNoMainSubject(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: null )
		)->execute( self::PAGE_ID );

		$this->assertAnsweredWithoutMainSubject( $presenter );
	}

	public function testUnreadablePageAnswersAsOneWithoutMainSubject(): void {
		$presenter = $this->newSpyPresenter();

		$this->newQuery(
			$presenter,
			$this->newRepositoryWithPage( mainSubject: TestSubject::build( id: self::MAIN_SUBJECT_ID ) ),
			readAuthorizer: new StubPageReadAuthorizer( allowed: false )
		)->execute( self::PAGE_ID );

		$this->assertAnsweredWithoutMainSubject( $presenter );
	}

	private function assertAnsweredWithoutMainSubject( object $presenter ): void {
		$this->assertEquals(
			new GetMainSubjectResponse( pageId: self::PAGE_ID, subject: null ),
			$presenter->response
		);
	}

	private function newRepositoryWithPage( ?Subject $mainSubject ): InMemorySubjectRepository {
		$repository = new InMemorySubjectRepository();

		$repository->savePageSubjects(
			new PageSubjects(
				$mainSubject,
				new SubjectMap( TestSubject::build( id: self::OTHER_SUBJECT_ID ) )
			),
			new PageId( self::PAGE_ID )
		);

		return $repository;
	}

	private function newQuery(
		GetMainSubjectPresenter $presenter,
		InMemorySubjectRepository $repository,
		?PageReadAuthorizer $readAuthorizer = null,
		?PageIdentifiersResolver $pageIdentifiersResolver = null,
	): GetMainSubjectQuery {
		return new GetMainSubjectQuery(
			presenter: $presenter,
			pageSubjectsLookup: new PageSubjectsLookup( $repository ),
			pageIdentifiersResolver: $pageIdentifiersResolver ?? $this->newResolverKnowingThePage(),
			readAuthorizer: $readAuthorizer ?? new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function newResolverKnowingThePage(): PageIdentifiersResolver {
		return new InMemoryPageIdentifiersResolver( [
			new PageIdentifiers( id: new PageId( self::PAGE_ID ), title: self::PAGE_TITLE, namespaceId: NS_MAIN ),
		] );
	}

	private function newSpyPresenter(): object {
		return new class() implements GetMainSubjectPresenter {

			public GetMainSubjectResponse $response;

			public function presentMainSubject( GetMainSubjectResponse $response ): void {
				$this->response = $response;
			}

		};
	}

}
