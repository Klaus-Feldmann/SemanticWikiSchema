<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\SubjectSummaries;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\PageSubjectsLookup;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummary;
use ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaryHostSubjects;
use ProfessionalWiki\SWS\Domain\Page\PageId;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Tests\Data\TestNaming;
use ProfessionalWiki\SWS\Tests\Data\TestRelation;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectRepository;

/**
 * @covers \ProfessionalWiki\SWS\Application\SubjectSummaries\SubjectSummaryHostSubjects
 */
class SubjectSummaryHostSubjectsTest extends TestCase {

	private const string PERSON = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const int PAGE_ID = 42;

	public function testASummaryOfADependentSubjectGetsTheHostSubjectItsPageNames(): void {
		$summaries = $this->newHostSubjects()->addTo( [ $this->summaryOf( self::BIRTH, 'Birth' ) ] );

		$this->assertSame( self::PERSON, $summaries[0]->hostSubject?->id );
	}

	private function newHostSubjects(): SubjectSummaryHostSubjects {
		$repository = new InMemorySubjectRepository();
		$repository->savePageSubjects(
			new PageSubjects(
				TestSubject::build(
					id: self::PERSON,
					label: 'Pablo Picasso',
					schemaName: TestSchema::reference( 'Person' ),
					statements: new StatementList( [
						TestStatement::buildRelation( 'Birth', [ TestRelation::build( targetId: self::BIRTH ) ] ),
					] )
				),
				new SubjectMap(
					TestSubject::build( id: self::BIRTH, label: null, schemaName: TestSchema::reference( 'Birth' ) ),
				)
			),
			new PageId( self::PAGE_ID )
		);

		$schemaLookup = new InMemorySchemaLookup(
			TestSchema::build( name: 'Person' ),
			TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
		);

		return new SubjectSummaryHostSubjects(
			TestNaming::newDependentSubjectRules( $schemaLookup ),
			TestNaming::newHostSubjectBuilder( $schemaLookup ),
			new PageSubjectsLookup( $repository )
		);
	}

	private function summaryOf( string $id, string $schema ): SubjectSummary {
		return new SubjectSummary(
			subjectId: $id,
			displayName: $schema,
			displayNameIsGenerated: true,
			schemaName: $schema,
			pageId: self::PAGE_ID,
			pageTitle: 'Pablo Picasso',
			lastEdited: '2026-10-14T00:00:00Z',
			hostSubject: null,
		);
	}

}
