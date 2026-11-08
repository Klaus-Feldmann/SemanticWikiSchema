<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\EntryPoints\REST;

use MediaWiki\Rest\RequestData;
use MediaWiki\Rest\ResponseInterface;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\SWS\Domain\Schema\SchemaName;
use ProfessionalWiki\SWS\Domain\Subject\SubjectMap;
use ProfessionalWiki\SWS\Infrastructure\AuthorityBasedRawQueryAuthorizer;
use ProfessionalWiki\SWS\SWSExtension;
use ProfessionalWiki\SWS\Tests\Data\TestSubject;
use ProfessionalWiki\SWS\Tests\SWSIntegrationTestCase;

/**
 * Needs the integration base class for the pages the Subjects are saved on and the database the counts are read from.
 *
 * @covers \ProfessionalWiki\SWS\EntryPoints\REST\GetSubjectCountsApi
 * @group Database
 */
class GetSubjectCountsApiTest extends SWSIntegrationTestCase {

	use HandlerTestTrait;

	public function setUp(): void {
		$this->createSchema( 'Computer' );
	}

	public function testCountsTheSubjectsOfEachSchema(): void {
		$this->createPageWithSubjects(
			'Home computers',
			TestSubject::build( id: 'sTestGSC1111111', schemaName: new SchemaName( 'Computer' ) ),
			new SubjectMap( TestSubject::build( id: 'sTestGSC1111112', schemaName: new SchemaName( 'Computer' ) ) )
		);

		$this->assertSame( '{"counts":{"Computer":2}}', $this->get()->getBody()->getContents() );
	}

	public function testAnswersAnEmptyObjectWhenNoSchemaHasSubjects(): void {
		$this->assertSame( '{"counts":{}}', $this->get()->getBody()->getContents() );
	}

	public function testDeniesACallerWithoutTheQueryRight(): void {
		$response = $this->executeHandler(
			SWSExtension::newGetSubjectCountsApi(),
			self::request(),
			authority: $this->mockAnonAuthorityWithoutPermissions( [ AuthorityBasedRawQueryAuthorizer::RIGHT ] )
		);

		$this->assertSame( 403, $response->getStatusCode() );
		$this->assertSame( 'permissionDenied', json_decode( $response->getBody()->getContents(), true )['errorType'] );
	}

	private function get(): ResponseInterface {
		return $this->executeHandler(
			SWSExtension::newGetSubjectCountsApi(),
			self::request(),
			authority: $this->mockAnonAuthorityWithPermissions( [ AuthorityBasedRawQueryAuthorizer::RIGHT ] )
		);
	}

	private static function request(): RequestData {
		return new RequestData( [ 'method' => 'GET' ] );
	}

}
