<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\SWS\Application\WikiConfig\ConfigValidator;

/**
 * @covers \ProfessionalWiki\SWS\Application\WikiConfig\ConfigValidator
 */
class ConfigValidatorTest extends TestCase {

	/**
	 * @return array[]
	 */
	private function validate( string $json ): array {
		return ( new ConfigValidator( new ConfigSchema() ) )->validate( $json );
	}

	public function testEmptyObjectIsValid(): void {
		$this->assertSame( [], $this->validate( '{}' ) );
	}

	public function testEverySettingPopulatedIsValid(): void {
		$this->assertSame(
			[],
			$this->validate( '{ "subjectFirst": true, "autoRenderMainSubject": false, "dataLanguages": [ "de", "fr", "it" ] }' )
		);
	}

	public function testSyntaxErrorIsLeftToCore(): void {
		$this->assertSame( [], $this->validate( '{ not valid json' ) );
	}

	public function testLiteralNullIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( 'null' ) );
	}

	public function testJsonArrayIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( '[ 1, 2 ]' ) );
	}

	public function testScalarJsonIsRejectedAsNotAnObject(): void {
		$this->assertSame( [ [ ConfigValidator::ERROR_NOT_OBJECT ] ], $this->validate( '42' ) );
	}

	public function testUnknownKeyIsRejected(): void {
		$this->assertSame(
			[ [ ConfigValidator::ERROR_UNKNOWN_KEY, 'SWSSparqlStores' ] ],
			$this->validate( '{ "SWSSparqlStores": [] }' )
		);
	}

	public function testWrongTypeForBooleanSettingIsRejected(): void {
		$this->assertSame(
			[ [ 'sws-config-error-invalid-boolean', 'autoRenderMainSubject' ] ],
			$this->validate( '{ "autoRenderMainSubject": "yes" }' )
		);
	}

	public function testWrongTypeForSubjectFirstSettingIsRejected(): void {
		$this->assertSame(
			[ [ 'sws-config-error-invalid-boolean', 'subjectFirst' ] ],
			$this->validate( '{ "subjectFirst": "yes" }' )
		);
	}

	public function testWrongShapeForDataLanguagesIsRejected(): void {
		$this->assertSame(
			[ [ 'sws-config-error-invalid-language-list', 'dataLanguages' ] ],
			$this->validate( '{ "dataLanguages": "de" }' )
		);
	}

	public function testEveryErrorIsReported(): void {
		$this->assertEqualsCanonicalizing(
			[
				[ ConfigValidator::ERROR_UNKNOWN_KEY, 'nope' ],
				[ 'sws-config-error-invalid-boolean', 'subjectFirst' ],
			],
			$this->validate( '{ "nope": 1, "subjectFirst": "x" }' )
		);
	}

}
