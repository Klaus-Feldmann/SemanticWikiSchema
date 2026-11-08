<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\Source;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\Source\LocalSource;
use ProfessionalWiki\SWS\Domain\Schema\Schema;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectIdList;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\SWS\Application\Source\LocalSource
 */
class LocalSourceTest extends TestCase {

	private const string BASE_URI = 'https://example.org/entity/';

	public function testServesTheSubjectsOfTheLocalStore(): void {
		$subject = TestSubject::build( id: 's11111111111111' );

		$this->assertSame(
			$subject,
			$this->newSource( subjects: [ $subject ] )->getSubject( new SubjectId( 's11111111111111' ) )
		);
	}

	public function testServesAWholeIdListInOneLookup(): void {
		$lookup = new InMemorySubjectLookup( TestSubject::build( id: 's11111111111111' ) );

		( new LocalSource( fn (): InMemorySubjectLookup => $lookup, new InMemorySchemaLookup(), self::BASE_URI ) )
			->getSubjects( new SubjectIdList( [ new SubjectId( 's11111111111111' ) ] ) );

		$this->assertSame( 1, $lookup->getSubjectsCallCount );
	}

	public function testServesTheSchemasOfTheLocalStore(): void {
		$schema = TestSchema::build( name: new SchemaName( 'City' ) );

		$this->assertSame(
			$schema,
			$this->newSource( schemas: [ $schema ] )->getSchema( new SchemaName( 'City' ) )
		);
	}

	public function testLocalSubjectsAreEditable(): void {
		$this->assertTrue( $this->newSource()->isEditable() );
	}

	public function testAcceptsOnlyNanoidsAsLocalIds(): void {
		$this->assertTrue( $this->newSource()->isValidLocalId( 's11111111111111' ) );
		$this->assertFalse( $this->newSource()->isValidLocalId( 'Q42' ) );
	}

	public function testReportsTheConfiguredBaseUri(): void {
		$this->assertSame( self::BASE_URI, $this->newSource()->getBaseUri() );
	}

	/**
	 * @param \ProfessionalWiki\SWS\Domain\Subject\Subject[] $subjects
	 * @param Schema[] $schemas
	 */
	private function newSource( array $subjects = [], array $schemas = [] ): LocalSource {
		$subjectLookup = new InMemorySubjectLookup( ...$subjects );

		return new LocalSource(
			fn (): InMemorySubjectLookup => $subjectLookup,
			new InMemorySchemaLookup( ...$schemas ),
			self::BASE_URI
		);
	}

}
