<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\Scribunto;

if ( !class_exists( \MediaWiki\Extension\Scribunto\Tests\Engines\LuaCommon\LuaEngineTestBase::class ) ) {
	return;
}

use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Schema\SchemaReference;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\Subject;
use ProfessionalWiki\SWS\Domain\Subject\SubjectId;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Domain\Value\StringValue;

/**
 * The library reads as the user the page is parsed for, which the engine under test makes the
 * anonymous user, while the request belongs to a sysop who may read and query everything.
 *
 * @group Lua
 * @group Database
 */
class SWSLibraryPermissionTest extends SWSLibraryTestBase {

	private const string RESTRICTED_PAGE = 'SWSLuaRestrictedPage';
	private const string RESTRICTED_SCHEMA = 'RestrictedSchema';

	// phpcs:ignore SlevomatCodingStandard.TypeHints.PropertyTypeHint.MissingAnyTypeHint -- parent class has no type hint
	protected static $moduleName = 'SWSLibraryPermissionTests';

	protected function setUp(): void {
		parent::setUp();

		$this->grantTheQueryRightToSysopsOnly();

		$this->createPageWithMainSubject(
			self::RESTRICTED_PAGE,
			mainSubject: new Subject(
				id: new SubjectId( 's1test5eeeeeeee' ),
				label: new SubjectLabel( 'Restricted Company' ),
				schema: SchemaReference::local( new SchemaName( 'Company' ) ),
				statements: new StatementList( [
					new Statement( new PropertyName( 'City' ), 'text', new StringValue( 'Secret City' ) ),
				] ),
			),
			otherSubjects: new SubjectMap(
				new Subject(
					id: new SubjectId( 's1test5ffffffff' ),
					label: new SubjectLabel( 'Restricted Entry' ),
					schema: SchemaReference::local( new SchemaName( 'Entry' ) ),
					statements: new StatementList(),
				),
			),
		);
		$this->createSchemaPage( self::RESTRICTED_SCHEMA, json_encode( [
			'description' => 'Readable by logged-in users only',
			'propertyDefinitions' => [ 'Name' => [ 'type' => 'text' ] ],
		] ) );
		$this->denyAnonymousReadOf( self::RESTRICTED_PAGE, 'Schema:' . self::RESTRICTED_SCHEMA );
	}

	protected function getTestModules(): array {
		return parent::getTestModules() + [
			'SWSLibraryPermissionTests' => __DIR__ . '/SWSLibraryPermissionTests.lua',
		];
	}

}
