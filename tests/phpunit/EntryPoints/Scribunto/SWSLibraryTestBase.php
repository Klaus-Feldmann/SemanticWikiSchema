<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Page\PageSubjects;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\NumberValue;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\Content\SchemaContent;
use ProfessionalWiki\SWS\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\SWS\EntryPoints\SWSRegistrar;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Tests\Data\TestSchema;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\ParseTimePermissionFixtures;
use ProfessionalWiki\SWS\Tests\TestDoubles\InMemorySource;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\Scribunto\ScribuntoLuaLibrary
 * @covers \ProfessionalWiki\SWS\EntryPoints\Scribunto\SubjectDataLookup
 * @covers \ProfessionalWiki\SWS\Application\SubjectResolver
 * @group Lua
 * @group Database
 */
abstract class SWSLibraryTestBase extends LuaEngineTestBase {

	use ParseTimePermissionFixtures;

	// phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint -- parent class has no type hint
	protected static $moduleName = 'SWSLibraryTests';

	/**
	 * Scribunto 1.46 onwards has each concrete test class name one engine. LuaStandalone is the
	 * pick because it needs no PHP extension and so is present wherever the suite runs, not
	 * because the library is specific to it.
	 */
	protected function getEngineName(): string {
		return 'LuaStandalone';
	}

	protected function setUp(): void {
		parent::setUp();

		$catalog = new InMemorySource();
		$catalog->addSchema( TestSchema::build( name: 'Widget' ) );

		$this->setTemporaryHook(
			'SWSRegistration',
			static function ( SWSRegistrar $registrar ) use ( $catalog ): void {
				$registrar->addSource( 'catalog', $catalog );
			}
		);

		// Registers nw.sparqlQuery in the Lua environment; the reset makes the extension rebuild with
		// the store, and with the Source above, before the engine loads the library.
		$this->configureAnUnreachableSparqlStore();

		// The Lua engine under test parses as the anonymous user. The request belongs to a sysop, so a
		// read that wrongly took the request's user or its tier shows up in the tests below.
		RequestContext::getMain()->setUser( $this->getTestSysop()->getUser() );
		$this->capTheDefaultTierAtOneRow();

		// Suppress SWS's revision handler during test data creation
		// to avoid graph DB and property type registration dependencies
		$this->setTemporaryHook( 'RevisionFromEditComplete', static function (): void {
		} );
		$this->createTestData();
	}

	protected function tearDown(): void {
		// The singleton bakes the store config; reset it so later tests rebuild from restored config.
		SWSExtension::resetInstance();
		parent::tearDown();
	}

	protected function getTestModules(): array {
		return parent::getTestModules() + [
			'SWSLibraryTests' => __DIR__ . '/SWSLibraryTests.lua',
		];
	}

	private function createTestData(): void {
		$this->createSchemaPage( 'Employee', json_encode( [
			'description' => 'A person employed by a company',
			'propertyDefinitions' => [
				'LegalName' => [ 'type' => 'text', 'required' => true ],
				'EmploymentFte' => [
					'type' => 'number',
					'minimum' => 0,
					'maximum' => 100,
					'default' => 100,
				],
				'Status' => [
					'type' => 'select',
					'required' => true,
					'options' => [
						[ 'id' => 'o1test1aaaaaaa1', 'label' => 'Active' ],
						[ 'id' => 'o1test1aaaaaaa2', 'label' => 'Inactive' ],
						[ 'id' => 'o1test1aaaaaaa3', 'label' => 'On leave' ],
					],
				],
				'Employer' => [
					'type' => 'relation',
					'targetSchemas' => [ 'Company', 'Organization' ],
				],
			],
		] ) );

		$this->createPageWithMainSubject(
			'SWSLuaTestPage',
			mainSubject: new Subject(
				id: new SubjectId( 's1test5aaaaaaaa' ),
				label: new SubjectLabel( 'Test Company' ),
				schema: SchemaReference::local( new SchemaName( 'Company' ) ),
				statements: new StatementList( [
					new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Berlin' ) ),
					new Statement( new PropertyName( 'Tags' ), 'text', new StringValue( 'alpha', 'beta', 'gamma' ) ),
					new Statement( new PropertyName( 'Founded' ), 'number', new NumberValue( 2019 ) ),
				] ),
			),
		);

		$this->createPageWithMainSubject(
			'SWSLuaTestPageOtherSubjects',
			mainSubject: new Subject(
				id: new SubjectId( 's1test5cccccccc' ),
				label: new SubjectLabel( 'Main' ),
				schema: SchemaReference::local( new SchemaName( 'Company' ) ),
				statements: new StatementList(),
			),
			otherSubjects: new SubjectMap(
				new Subject(
					id: new SubjectId( 's1test5dddddddd' ),
					label: new SubjectLabel( 'Other Entry' ),
					schema: SchemaReference::local( new SchemaName( 'Entry' ) ),
					statements: new StatementList( [
						new Statement( new PropertyName( 'Note' ), 'text', new StringValue( 'Another subject on the page' ) ),
					] ),
				),
			),
		);

		// The test modules run on this page, so a read that fell back to it would find this Subject.
		$this->createPageWithMainSubject( $this->getTestTitle()->getPrefixedText(), mainSubject: TestSubject::build() );
	}

	protected function createSchemaPage( string $name, string $json ): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $name, SWSExtension::NS_SCHEMA )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new SchemaContent( $json ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Lua test data' ) );
	}

	protected function createPageWithMainSubject(
		string $pageName,
		Subject $mainSubject,
		SubjectMap $otherSubjects = new SubjectMap(),
	): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $pageName )
		);

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent(
			MediaWikiSubjectRepository::SLOT_NAME,
			SubjectContent::newFromData( new PageSubjects( $mainSubject, $otherSubjects ) ),
		);

		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Lua test data' ) );
	}

}
