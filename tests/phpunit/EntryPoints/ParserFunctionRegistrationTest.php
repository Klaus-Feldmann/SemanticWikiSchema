<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\EntryPoints;

use MediaWiki\Parser\Parser;
use AEWI\SWS\EntryPoints\SWSHooks;
use AEWI\SWS\Tests\SWSIntegrationTestCase;

/**
 * @covers \AEWI\SWS\EntryPoints\SWSHooks::onParserFirstCallInit
 * @group Database
 */
class ParserFunctionRegistrationTest extends SWSIntegrationTestCase {

	public function testFunctionsNeedingNoGraphStoreRegisterWithoutOne(): void {
		$this->runWithoutGraphBackend( function (): void {
			$parser = $this->recordingParser( $names );

			SWSHooks::onParserFirstCallInit( $parser );

			$this->assertContains( 'view', $names );
			$this->assertContains( 'sws_value', $names );
			$this->assertContains( 'create_subject', $names );
		} );
	}

	private function recordingParser( ?array &$names ): Parser {
		$names = [];
		$parser = $this->createMock( Parser::class );
		$parser->method( 'setFunctionHook' )->willReturnCallback(
			static function ( string $name ) use ( &$names ): void {
				$names[] = $name;
			}
		);
		return $parser;
	}

}
