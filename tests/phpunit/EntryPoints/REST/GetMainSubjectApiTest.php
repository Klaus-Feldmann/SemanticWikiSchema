<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\REST;

use MediaWiki\Permissions\Authority;
use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\Response;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\StatementList;
use ProfessionalWiki\SWS\Domain\Subject\SubjectLabel;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\EntryPoints\REST\GetMainSubjectApi;
use ProfessionalWiki\SWS\Tests\Data\TestStatement;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;
use ProfessionalWiki\SWS\Tests\SWSMockAuthorityTrait;

/**
 * Runs the handler against pages saved through MediaWiki, which is where the page's title and the
 * viewer's page-level read permission come from.
 *
 * @covers \ProfessionalWiki\SWS\EntryPoints\REST\GetMainSubjectApi
 * @covers \ProfessionalWiki\SWS\Presentation\RestGetMainSubjectPresenter
 * @covers \ProfessionalWiki\SWS\Application\Queries\GetMainSubject\GetMainSubjectQuery
 * @group Database
 */
class GetMainSubjectApiTest extends SWSIntegrationTestCase {
	use HandlerTestTrait;
	use SWSMockAuthorityTrait;

	public function setUp(): void {
		$this->setUpNeo4j();

		$this->createSchema( 'GetMainSubjectApiTestSchema' );
	}

	public function testServesTheMainSubjectWithItsPage(): void {
		$pageId = $this->createPageWithSubjects(
			'GetMainSubjectApiTest_Berlin',
			mainSubject: TestSubject::build(
				id: 'sTestGMS1111111',
				label: new SubjectLabel( 'Berlin' ),
				schemaName: new SchemaName( 'GetMainSubjectApiTestSchema' ),
				statements: new StatementList( [
					TestStatement::build( 'population', '3700000' ),
				] )
			),
			otherSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGMS1111112',
					schemaName: new SchemaName( 'GetMainSubjectApiTestSchema' )
				),
			),
		)->getPage()->getId();

		$response = $this->getMainSubject( $pageId );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"pageId": $pageId,
	"subject": {
		"id": "sTestGMS1111111",
		"label": "Berlin",
		"displayName": "Berlin",
		"displayNameIsGenerated": false,
		"schema": "GetMainSubjectApiTestSchema",
		"pageId": $pageId,
		"pageTitle": "GetMainSubjectApiTest Berlin",
		"pageNamespaceId": 0,
		"statements": {
			"population": { "propertyType": "text", "value": [ "3700000" ] }
		}
	}
}
JSON,
			$response->getBody()->getContents()
		);
	}

	public function testPageWithOnlyOtherSubjectsHasNoMainSubject(): void {
		$pageId = $this->createPageWithSubjects(
			'GetMainSubjectApiTest_NoMain',
			mainSubject: null,
			otherSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestGMS1111131',
					schemaName: new SchemaName( 'GetMainSubjectApiTestSchema' )
				),
			),
		)->getPage()->getId();

		$this->assertAnswersWithoutMainSubject( $pageId );
	}

	public function testNonexistentPageHasNoMainSubject(): void {
		$this->assertAnswersWithoutMainSubject( 999999 );
	}

	/**
	 * Denial answers as a page without a Main Subject does, so the endpoint cannot be used to probe
	 * page readability (#1046).
	 */
	public function testMainSubjectOnAnUnreadablePageIsAbsent(): void {
		$restrictedPageId = $this->createPageWithSubjects(
			'GetMainSubjectApiTest_Restricted',
			mainSubject: TestSubject::build(
				id: 'sTestGMS1111141',
				schemaName: new SchemaName( 'GetMainSubjectApiTestSchema' )
			)
		)->getPage()->getId();

		$this->assertAnswersWithoutMainSubject( $restrictedPageId, $this->authorityWithGlobalReadButNoPageRead() );
	}

	private function assertAnswersWithoutMainSubject( int $pageId, ?Authority $authority = null ): void {
		$response = $this->getMainSubject( $pageId, $authority );

		$this->assertSame( 200, $response->getStatusCode() );
		$this->assertJsonStringEqualsJsonString(
			'{"pageId":' . $pageId . ',"subject":null}',
			$response->getBody()->getContents()
		);
	}

	private function getMainSubject( int $pageId, ?Authority $authority = null ): Response {
		return $this->executeHandler(
			new GetMainSubjectApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'pageId' => (string)$pageId ],
			] ),
			authority: $authority
		);
	}

}
