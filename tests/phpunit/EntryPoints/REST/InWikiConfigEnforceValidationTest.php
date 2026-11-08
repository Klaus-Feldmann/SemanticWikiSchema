<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\REST;

use MediaWiki\Content\JsonContent;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\Title\Title;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\EntryPoints\REST\ReplaceSubjectApi;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Presentation\CsrfValidator;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\SWS\EntryPoints\REST\ReplaceSubjectApi
 * @covers \ProfessionalWiki\SWS\Application\WikiConfig\WikiConfigLookup
 * @group Database
 */
class InWikiConfigEnforceValidationTest extends SWSIntegrationTestCase {
	use HandlerTestTrait;

	private const string SCHEMA = 'InWikiConfigEnforcementSchema';
	private const string SUBJECT_ID = 'sCfgEnforce1111';

	public function testEnforcementSetOnTheConfigurationPageBlocksAWriteThatAddsAnErrorViolation(): void {
		$this->overrideConfigValue( 'SWSEnforceValidation', false );
		$this->createSubjectWithItsRequiredStatement();
		$this->saveConfigPage( '{ "enforceValidation": true }' );

		$this->assertSame( 422, $this->replaceSubjectWithoutItsRequiredStatement()->getStatusCode() );
	}

	private function createSubjectWithItsRequiredStatement(): void {
		$this->createSchema(
			self::SCHEMA,
			'{"title":"' . self::SCHEMA . '","propertyDefinitions":{"Required":{"type":"text","required":{"severity":"error"}}}}'
		);

		$this->createPageWithSubjects(
			'InWikiConfigEnforcement',
			mainSubject: TestSubject::build(
				id: self::SUBJECT_ID,
				schemaName: new SchemaName( self::SCHEMA ),
				statements: new StatementList( [
					TestStatement::build( property: 'Required', value: 'present' ),
				] )
			)
		);
	}

	private function saveConfigPage( string $json ): void {
		$this->editPage( Title::makeTitle( NS_MEDIAWIKI, SWSExtension::CONFIG_PAGE_TITLE ), new JsonContent( $json ) );

		// The source memoizes the page per request, so drop the singleton to read the just-saved page.
		SWSExtension::resetInstance();
	}

	private function replaceSubjectWithoutItsRequiredStatement(): Response {
		$csrfValidator = $this->createStub( CsrfValidator::class );
		$csrfValidator->method( 'verifyCsrfToken' )->willReturn( true );

		return $this->executeHandler(
			new ReplaceSubjectApi( csrfValidator: $csrfValidator ),
			new RequestData( [
				'method' => 'PUT',
				'pathParams' => [ 'subjectId' => self::SUBJECT_ID ],
				'bodyContents' => json_encode( [ 'label' => 'Without', 'statements' => [] ] ),
				'headers' => [ 'Content-Type' => 'application/json' ],
			] )
		);
	}

}
