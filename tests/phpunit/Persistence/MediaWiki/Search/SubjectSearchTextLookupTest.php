<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Persistence\MediaWiki\Search;

use AEWI\SWS\Persistence\MediaWiki\Search\SubjectSearchTextLookup;
use AEWI\SWS\Domain\Subject\StatementList;
use AEWI\SWS\Domain\Subject\Subject;
use AEWI\SWS\SWSExtension;
use AEWI\SWS\Tests\Data\TestStatement;
use AEWI\SWS\Tests\Data\TestSubject;
use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * @group Database
 * @covers \AEWI\SWS\Persistence\MediaWiki\Search\SubjectSearchTextLookup
 */
class SubjectSearchTextLookupTest extends SWSIntegrationTestCase {

	public function testReturnsLabelAndValuesOfThePagesSubjects(): void {
		$revision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $revision );

		$this->assertSame(
			"Rijksmuseum\nAmsterdam",
			$this->newLookup()->getSearchTextForPage( $revision->getPageId() )
		);
	}

	public function testPageWithoutSubjectsHasNoSearchText(): void {
		$pageId = $this->insertPage( 'Plain page', 'Just wikitext' )['id'];

		$this->assertSame( '', $this->newLookup()->getSearchTextForPage( $pageId ) );
	}

	public function testUnknownPageHasNoSearchText(): void {
		$this->assertSame( '', $this->newLookup()->getSearchTextForPage( 4040404 ) );
	}

	public function testReadsTheLatestRevisionOfThePage(): void {
		$firstRevision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $firstRevision );
		$this->changeSubjectsOfPage( 'Rijksmuseum', $this->museumSubject( 'Rotterdam' ) );

		$this->assertSame(
			"Rijksmuseum\nRotterdam",
			$this->newLookup()->getSearchTextForPage( $firstRevision->getPageId() )
		);
	}

	public function testReadsTheRevisionItIsHandedRatherThanTheLatestOne(): void {
		$firstRevision = $this->createPageWithSubjects( 'Rijksmuseum', $this->museumSubject( 'Amsterdam' ) );
		$this->assertNotNull( $firstRevision );
		$this->changeSubjectsOfPage( 'Rijksmuseum', $this->museumSubject( 'Rotterdam' ) );

		$this->assertSame(
			"Rijksmuseum\nAmsterdam",
			$this->newLookup()->getSearchTextForRevision( $firstRevision )
		);
	}

	private function museumSubject( string $city ): Subject {
		return TestSubject::build(
			label: 'Rijksmuseum',
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	private function newLookup(): SubjectSearchTextLookup {
		return SWSExtension::getInstance()->newSubjectSearchTextLookup();
	}

}
