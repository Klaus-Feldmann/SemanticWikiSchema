<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\SWS\Application\NullRevisionPolicy;
use ProfessionalWiki\SWS\Application\RevisionPolicy;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageIdentifiers;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\PublishedSubjectLookup;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemoryPageIdentifiersLookup;

/**
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\PublishedSubjectLookup
 * @group Database
 */
class PublishedSubjectLookupTest extends SWSIntegrationTestCase {

	private function newLookup(
		RevisionPolicy $revisionPolicy,
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup,
		?RevisionLookup $revisionLookup = null,
	): PublishedSubjectLookup {
		return new PublishedSubjectLookup(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $revisionLookup ?? MediaWikiServices::getInstance()->getRevisionLookup(),
			revisionPolicy: $revisionPolicy,
		);
	}

	public function testReadsEachHostingPageAtTheRevisionItPublishes(): void {
		$firstPublished = TestSubject::build( id: 'sPubTest1111111', label: new SubjectLabel( 'First published' ) );
		$secondPublished = TestSubject::build( id: 'sPubTest1111112', label: new SubjectLabel( 'Second published' ) );
		$onOtherPage = TestSubject::build( id: 'sPubTest1111113', label: new SubjectLabel( 'On the other page' ) );

		$publishedRevision = $this->createPageWithSubjects(
			'PublishedTestApproved',
			mainSubject: $firstPublished,
			otherSubjects: new SubjectMap( $secondPublished ),
		);

		$this->createPageWithSubjects(
			'PublishedTestApproved',
			mainSubject: TestSubject::build( id: 'sPubTest1111111', label: new SubjectLabel( 'First draft' ) ),
			otherSubjects: new SubjectMap( TestSubject::build( id: 'sPubTest1111112', label: new SubjectLabel( 'Second draft' ) ) ),
		);

		$otherRevision = $this->createPageWithSubjects( 'PublishedTestOther', mainSubject: $onOtherPage );

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$this->registerHostingPage( $pageIdentifiersLookup, $firstPublished->id, $publishedRevision, 'PublishedTestApproved' );
		$this->registerHostingPage( $pageIdentifiersLookup, $secondPublished->id, $publishedRevision, 'PublishedTestApproved' );
		$this->registerHostingPage( $pageIdentifiersLookup, $onOtherPage->id, $otherRevision, 'PublishedTestOther' );

		$subjects = $this->newLookup( FixedRevisionPolicy::publishing( $publishedRevision ), $pageIdentifiersLookup )
			->getSubjects( new SubjectIdList( [ $firstPublished->id, $secondPublished->id, $onOtherPage->id ] ) );

		$this->assertEqualsCanonicalizing(
			[ $firstPublished, $secondPublished, $onOtherPage ],
			$subjects->asArray()
		);
	}

	public function testYieldsNoSubjectsForAPageThatPublishesNothing(): void {
		$subject = TestSubject::build( id: 'sPubTest1111114' );
		$revision = $this->createPageWithSubjects( 'PublishedTestNothing', mainSubject: $subject );

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$this->registerHostingPage( $pageIdentifiersLookup, $subject->id, $revision, 'PublishedTestNothing' );

		$subjects = $this->newLookup( FixedRevisionPolicy::publishingNothing(), $pageIdentifiersLookup )
			->getSubjects( new SubjectIdList( [ $subject->id ] ) );

		$this->assertTrue( $subjects->isEmpty() );
	}

	/**
	 * @dataProvider subjectJsonThatDoesNotDeserializeProvider
	 */
	public function testYieldsNoPageSubjectsForAPageWhosePublishedSubjectDataDoesNotDeserialize( string $subjectJson ): void {
		$revision = $this->writeSubjectSlot( 'PublishedTestBroken', $subjectJson );
		$this->assertNotNull( $revision );

		$pageSubjects = $this->newLookup( new NullRevisionPolicy(), new InMemoryPageIdentifiersLookup() )
			->getPageSubjects( new PageId( $revision->getPageId() ) );

		$this->assertTrue( $pageSubjects->isEmpty() );
	}

	public static function subjectJsonThatDoesNotDeserializeProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( 'sPubTest1111116' ) ];
		yield 'a Subject that is not an object' => [ '{"mainSubject":"sPubTest1111116","subjects":{"sPubTest1111116":1}}' ];
	}

	public function testEmptyIdListReadsNoRevision(): void {
		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->expects( $this->never() )->method( 'getRevisionByPageId' );

		$subjects = $this
			->newLookup( FixedRevisionPolicy::publishingNothing(), new InMemoryPageIdentifiersLookup(), $revisionLookup )
			->getSubjects( new SubjectIdList( [] ) );

		$this->assertTrue( $subjects->isEmpty() );
	}

	public function testReturnsNullForASubjectHostedByNoPage(): void {
		$lookup = $this->newLookup( FixedRevisionPolicy::publishingNothing(), new InMemoryPageIdentifiersLookup() );

		$this->assertNull( $lookup->getSubject( new SubjectId( 'sPubTest1111115' ) ) );
	}

	private function registerHostingPage(
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup,
		SubjectId $subjectId,
		RevisionRecord $revision,
		string $pageName
	): void {
		$pageIdentifiersLookup->addIdentifiers(
			$subjectId,
			new PageIdentifiers( new PageId( $revision->getPage()->getId() ), $pageName, 0 )
		);
	}

}
