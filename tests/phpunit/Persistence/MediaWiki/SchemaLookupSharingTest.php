<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\SWS\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\SWS\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\SWS\Persistence\MediaWiki\PublishedPageJsonLookup;
use ProfessionalWiki\SWS\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\SWS\Tests\Data\TestPage;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\TestDoubles\SpyPageContentFetcher;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use ProfessionalWiki\SWS\Tests\Data\TestSources;

/**
 * The Schema of a Subject is needed by both the validation and the graph projection of every
 * Subject on a saved page. These pin that a page's Schemas are resolved once per Schema, not
 * once per Subject per path.
 *
 * @covers \ProfessionalWiki\SWS\Persistence\MediaWiki\CachingSchemaLookup
 * @covers \ProfessionalWiki\SWS\SWSExtension
 * @group Database
 */
class SchemaLookupSharingTest extends SWSIntegrationTestCase {

	private const SCHEMA_NAME = 'SharedLookupSchema';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA_NAME );
	}

	public function testProjectingAPageResolvesItsSchemaOnce(): void {
		$fetcher = $this->newFetcher();

		$this->newCountingProjectionStore( $fetcher )->savePage(
			TestPage::build(
				mainSubject: $this->newSubject( 'sShrdLookup1111' ),
				otherSubjects: new SubjectMap(
					$this->newSubject( 'sShrdLookup1112' ),
					$this->newSubject( 'sShrdLookup1113' ),
					$this->newSubject( 'sShrdLookup1114' ),
				)
			)
		);

		$this->assertSame( 1, $fetcher->fetchCount );
	}

	public function testValidatingSubjectsResolvesTheirSchemaOnce(): void {
		$fetcher = $this->newFetcher();
		$validator = new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( $this->newCachingLookup( $fetcher ) ),
			subjectValidator: SWSExtension::getInstance()->newSubjectValidator( $this->getTestUser()->getAuthority() ),
		);

		$validator->validate( $this->newSubject( 'sShrdLookup1115' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1116' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1117' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1118' ) );

		$this->assertSame( 1, $fetcher->fetchCount );
	}

	public function testServesOneSchemaLookupThroughoutARequest(): void {
		$extension = SWSExtension::getInstance();

		$this->assertSame( $extension->getSchemaLookup(), $extension->getSchemaLookup() );
	}

	/**
	 * The parser holds what the normalizer remembers, so a fresh one per read would parse every Schema
	 * name again on every page.
	 */
	public function testServesOneSchemaReferenceParserThroughoutARequest(): void {
		$extension = SWSExtension::getInstance();

		$this->assertSame( $extension->getSchemaReferenceParser(), $extension->getSchemaReferenceParser() );
	}

	private function newCountingProjectionStore( SpyPageContentFetcher $fetcher ): GraphDatabasePlugin {
		return SWSExtension::getInstance()->newNeo4jProjectionStore( $this->newCachingLookup( $fetcher ) );
	}

	private function newCachingLookup( SpyPageContentFetcher $fetcher ): CachingSchemaLookup {
		return new CachingSchemaLookup(
			jsonLookup: new PublishedPageJsonLookup(
				$fetcher,
				$this->getTestUser()->getAuthority(),
				// Stores nothing, so every read past the process-local tier reaches the counting fetcher.
				new WANObjectCache( [ 'cache' => new EmptyBagOStuff() ] )
			),
			schemaDeserializer: new SchemaPersistenceDeserializer(
				SWSExtension::getInstance()->getPropertyTypeLookup()
			),
			titleFactory: MediaWikiServices::getInstance()->getTitleFactory(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
		);
	}

	private function newSubject( string $id ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( self::SCHEMA_NAME ) );
	}

	private function newFetcher(): SpyPageContentFetcher {
		return new SpyPageContentFetcher( new SchemaContent( '{"description":"","propertyDefinitions":{}}' ) );
	}

}
