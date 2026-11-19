<?php

declare( strict_types = 1 );

namespace AEWI\SWS\Tests\Presentation;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use AEWI\SWS\Application\WikiConfig\ConfigSchema;
use AEWI\SWS\Application\WikiConfig\WikiConfigLookup;
use AEWI\SWS\Presentation\ConfigEditorJsConfigBuilder;
use AEWI\SWS\Tests\TestDoubles\StubWikiConfigSource;

/**
 * @covers \AEWI\SWS\Presentation\ConfigEditorJsConfigBuilder
 */
class ConfigEditorJsConfigBuilderTest extends TestCase {

	private const array PHP_CONFIG = [
		'SWSSubjectFirst' => false,
		'SWSAutoRenderMainSubject' => true,
		'SWSDataLanguages' => [],
		'SWSEnforceValidation' => false,
	];

	private function buildFor( ?array $pageConfig, array $phpConfig = self::PHP_CONFIG ): array {
		$builder = new ConfigEditorJsConfigBuilder(
			new ConfigSchema(),
			new WikiConfigLookup(
				new ConfigSchema(),
				new StubWikiConfigSource( $pageConfig ),
				new HashConfig( $phpConfig ),
				true,
				new NullLogger()
			)
		);

		return $builder->build( revisionId: 42 );
	}

	public function testGivesTheExposedSettingsInTheirOrder(): void {
		$jsConfig = $this->buildFor( null );

		$this->assertSame( [ 'subjectFirst', 'dataLanguages', 'enforceValidation', 'autoRenderMainSubject' ], array_keys( $jsConfig['pageValues'] ) );
		$this->assertSame(
			[ 'subjectFirst', 'dataLanguages', 'enforceValidation', 'autoRenderMainSubject' ],
			array_keys( $jsConfig['localSettingsValues'] )
		);
	}

	public function testGivesThePageValueOfASettingThePageSets(): void {
		$this->assertFalse( $this->buildFor( [ 'autoRenderMainSubject' => false ] )['pageValues']['autoRenderMainSubject'] );
	}

	public function testGivesNoPageValueForASettingThePageDoesNotSet(): void {
		$this->assertNull( $this->buildFor( [ 'autoRenderMainSubject' => false ] )['pageValues']['subjectFirst'] );
	}

	public function testGivesNoPageValueForASettingThePageSetsToAValueItDoesNotAccept(): void {
		$this->assertNull( $this->buildFor( [ 'subjectFirst' => 'yes' ] )['pageValues']['subjectFirst'] );
	}

	public function testGivesTheLocalSettingsValueWhateverThePageSets(): void {
		$jsConfig = $this->buildFor(
			[ 'dataLanguages' => [ 'fr' ] ],
			[ 'SWSDataLanguages' => [ 'de', 'it' ] ] + self::PHP_CONFIG
		);

		$this->assertSame( [ 'de', 'it' ], $jsConfig['localSettingsValues']['dataLanguages'] );
	}

}
