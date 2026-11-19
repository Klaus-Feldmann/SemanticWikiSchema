<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\EntryPoints\REST;

use MediaWiki\Rest\HttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use AEWI\SWS\EntryPoints\REST\GetSchemaNamesApi;
use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \AEWI\SWS\EntryPoints\REST\GetSchemaNamesApi
 * @group Database
 */
class GetSchemaNamesApiTest extends SWSIntegrationTestCase {
	use HandlerTestTrait;

	public function testRejectsRequestWithoutSearchParam(): void {
		$this->expectException( HttpException::class );
		$this->expectExceptionCode( 400 );

		$this->executeHandler(
			new GetSchemaNamesApi(),
			new RequestData( [ 'method' => 'GET' ] )
		);
	}

	public function testReturnsMatchingSchemaNames(): void {
		$this->createSchema( 'FooSchema' );
		$this->createSchema( 'FooBarSchema' );
		$this->createSchema( 'BazSchema' );

		$response = $this->executeHandler(
			new GetSchemaNamesApi(),
			new RequestData( [
				'method' => 'GET',
				'pathParams' => [ 'search' => 'Foo' ],
			] )
		);

		$this->assertSame( 200, $response->getStatusCode() );
		$names = json_decode( $response->getBody()->getContents(), true );
		$this->assertContains( 'FooSchema', $names );
		$this->assertContains( 'FooBarSchema', $names );
		$this->assertNotContains( 'BazSchema', $names );
	}

}
