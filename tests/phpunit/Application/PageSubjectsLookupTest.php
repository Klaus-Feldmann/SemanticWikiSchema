<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\PageSubjectsLookup;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectRepository;

/**
 * @covers \ProfessionalWiki\SWS\Application\PageSubjectsLookup
 */
class PageSubjectsLookupTest extends TestCase {

	private const int PAGE_ID = 42;
	private const int OTHER_PAGE_ID = 43;

	public function testPageHasSubjectsIsTrueWhenMainSubjectExists(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertTrue( $lookup->pageHasSubjects( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasSubjectsIsTrueWhenOnlyOtherSubjectsExist(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build() ) )
		);

		$this->assertTrue( $lookup->pageHasSubjects( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasSubjectsIsFalseForOtherPage(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertFalse( $lookup->pageHasSubjects( new PageId( self::OTHER_PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsTrueWhenMainSubjectExists(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertTrue( $lookup->pageHasMainSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsFalseWhenOnlyOtherSubjectsExist(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( null, new SubjectMap( TestSubject::build() ) )
		);

		$this->assertFalse( $lookup->pageHasMainSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testPageHasMainSubjectIsFalseForOtherPage(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertFalse( $lookup->pageHasMainSubject( new PageId( self::OTHER_PAGE_ID ) ) );
	}

	public function testGetPageSubjectsReturnsTheSubjectsOfThePage(): void {
		$pageSubjects = new PageSubjects(
			TestSubject::build( id: 's11111111111maa' ),
			new SubjectMap( TestSubject::build( id: 's11111111111ca1' ) )
		);

		$lookup = $this->newLookupWithSubjects( $pageSubjects );

		$this->assertEquals( $pageSubjects, $lookup->getPageSubjects( new PageId( self::PAGE_ID ) ) );
	}

	public function testGetPageSubjectsIsEmptyForPageWithoutSubjects(): void {
		$lookup = $this->newLookupWithSubjects(
			new PageSubjects( TestSubject::build(), new SubjectMap() )
		);

		$this->assertEquals(
			PageSubjects::newEmpty(),
			$lookup->getPageSubjects( new PageId( self::OTHER_PAGE_ID ) )
		);
	}

	private function newLookupWithSubjects( PageSubjects $subjects ): PageSubjectsLookup {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects( $subjects, new PageId( self::PAGE_ID ) );

		return new PageSubjectsLookup( $repository );
	}

}
