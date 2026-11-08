<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints;

use MediaWiki\Extension\Scribunto\ScribuntoContent;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Schema\PropertyName;
use ProfessionalWiki\SWS\Domain\Statement;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Value\StringValue;
use ProfessionalWiki\SWS\EntryPoints\SWSHooks;
use ProfessionalWiki\SWS\EntryPoints\Scribunto\ScribuntoLuaLibrary;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\SWSHooks::onScribuntoExternalLibraries
 * @covers \ProfessionalWiki\SWS\SWSExtension::isLuaEnabled
 * @group Database
 */
class LuaLibraryRegistrationTest extends SWSIntegrationTestCase {

	private const string SUBJECT_PAGE = 'LuaLibraryRegistrationTestSubject';
	private const string MODULE = 'LuaLibraryRegistrationTestModule';
	private const string MOTTO = 'Read from the Subject';

	private const string LIBRARY_MODULE = "local nw = require( 'mw.sws' )\n"
		. "local p = {}\n"
		. "function p.motto( frame ) return nw.getValue( 'Motto', { page = frame.args[1] } ) or '' end\n"
		. 'return p';

	private const string PARSER_FUNCTION_MODULE = "local p = {}\n"
		. "function p.motto( frame )\n"
		. "	return frame:preprocess( '{{#sws_value: Motto | page=' .. frame.args[1] .. ' }}' )\n"
		. "end\n"
		. 'return p';

	protected function setUp(): void {
		parent::setUp();

		$this->createPageWithSubjects(
			self::SUBJECT_PAGE,
			TestSubject::build( statements: new StatementList( [
				new Statement( new PropertyName( 'Motto' ), 'text', new StringValue( self::MOTTO ) ),
			] ) )
		);
	}

	public function testTheLibraryIsRegistered(): void {
		$libraries = [];

		SWSHooks::onScribuntoExternalLibraries( 'lua', $libraries );

		$this->assertSame( [ 'mw.sws' => ScribuntoLuaLibrary::class ], $libraries );
	}

	public function testNothingIsRegisteredWhenTheLibraryIsDisabled(): void {
		$this->overrideConfigValue( 'SWSEnableLua', false );
		$libraries = [];

		SWSHooks::onScribuntoExternalLibraries( 'lua', $libraries );

		$this->assertSame( [], $libraries );
	}

	public function testNothingIsRegisteredWithAnotherEngine(): void {
		$libraries = [];

		SWSHooks::onScribuntoExternalLibraries( 'php', $libraries );

		$this->assertSame( [], $libraries );
	}

	public function testAModuleReadsASubjectThroughTheLibrary(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );

		$html = $this->renderModule( self::LIBRARY_MODULE );

		$this->assertStringContainsString( self::MOTTO, $html );
		$this->assertStringNotContainsString( 'scribunto-error', $html );
	}

	/**
	 * Naming the library in the error tells a "module not found" for mw.sws apart from one for the
	 * module under test, which would report the same way.
	 */
	public function testRequiringTheLibraryFailsWhenItIsDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->overrideConfigValue( 'SWSEnableLua', false );

		$html = $this->renderModule( self::LIBRARY_MODULE );

		$this->assertStringNotContainsString( self::MOTTO, $html );
		$this->assertStringContainsString( 'scribunto-error', $html );
		$this->assertStringContainsString( 'mw.sws', $html );
	}

	/**
	 * The parser functions stay registered, so a module that expands one still reads the same data.
	 */
	public function testAModuleStillReachesTheParserFunctionsWhenTheLibraryIsDisabled(): void {
		$this->markTestSkippedIfExtensionNotLoaded( 'Scribunto' );
		$this->overrideConfigValue( 'SWSEnableLua', false );

		$html = $this->renderModule( self::PARSER_FUNCTION_MODULE );

		$this->assertStringContainsString( self::MOTTO, $html );
		$this->assertStringNotContainsString( 'scribunto-error', $html );
	}

	/**
	 * What a reader of a page invoking $moduleCode against the Subject's page ends up with.
	 */
	private function renderModule( string $moduleCode ): string {
		$this->editPage( Title::makeTitle( NS_MODULE, self::MODULE ), new ScribuntoContent( $moduleCode ) );

		return $this->parseWikitextOn(
			'LuaLibraryRegistrationTestPage',
			'{{#invoke:' . self::MODULE . '|motto|' . self::SUBJECT_PAGE . '}}'
		);
	}

}
