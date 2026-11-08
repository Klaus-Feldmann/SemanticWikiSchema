<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\GraphDatabasePlugins\Neo4j\Application\Exception;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException;
use ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException;

/**
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\EmptyQueryException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\WriteQueryRejectedException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\CypherSyntaxException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\ParameterMissingException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\QueryTimeoutException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\BackendUnavailableException
 * @covers \ProfessionalWiki\SWS\GraphDatabasePlugins\Neo4j\Application\Exception\InternalQueryException
 */
class QueryExceptionTest extends TestCase {

	public function testEmptyQueryExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new EmptyQueryException() );
	}

	public function testEmptyQueryExceptionErrorType(): void {
		$this->assertSame( 'emptyQuery', ( new EmptyQueryException() )->errorType() );
	}

	public function testWriteQueryRejectedExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new WriteQueryRejectedException() );
	}

	public function testWriteQueryRejectedExceptionErrorType(): void {
		$this->assertSame( 'writeQueryRejected', ( new WriteQueryRejectedException() )->errorType() );
	}

	public function testCypherSyntaxExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new CypherSyntaxException() );
	}

	public function testCypherSyntaxExceptionErrorType(): void {
		$this->assertSame( 'cypherSyntaxError', ( new CypherSyntaxException() )->errorType() );
	}

	public function testParameterMissingExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new ParameterMissingException() );
	}

	public function testParameterMissingExceptionErrorType(): void {
		$this->assertSame( 'parameterMissing', ( new ParameterMissingException() )->errorType() );
	}

	public function testQueryTimeoutExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new QueryTimeoutException() );
	}

	public function testQueryTimeoutExceptionErrorType(): void {
		$this->assertSame( 'queryTimeout', ( new QueryTimeoutException() )->errorType() );
	}

	public function testBackendUnavailableExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new BackendUnavailableException() );
	}

	public function testBackendUnavailableExceptionErrorType(): void {
		$this->assertSame( 'backendUnavailable', ( new BackendUnavailableException() )->errorType() );
	}

	public function testInternalQueryExceptionExtendsBase(): void {
		$this->assertInstanceOf( QueryException::class, new InternalQueryException() );
	}

	public function testInternalQueryExceptionErrorType(): void {
		$this->assertSame( 'internalError', ( new InternalQueryException() )->errorType() );
	}

}
