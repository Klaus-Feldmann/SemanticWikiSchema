<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\WikiConfig;

use MediaWiki\Config\HashConfig;
use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use ProfessionalWiki\SWS\Application\WikiConfig\ConfigSchema;
use ProfessionalWiki\SWS\Application\WikiConfig\WikiConfigLookup;
use ProfessionalWiki\SWS\Tests\TestDoubles\StubWikiConfigSource;

/**
 * @covers \ProfessionalWiki\SWS\Application\WikiConfig\WikiConfigLookup
 */
class WikiConfigLookupTest extends TestCase {

	private const PHP_CONFIG = [
		'SWSSubjectFirst' => false,
		'SWSAutoRenderMainSubject' => true,
		'SWSDataLanguages' => [],
	];

	private function newLookup(
		?array $pageData,
		bool $enabled = true,
		?TestLogger $logger = null,
		array $phpConfig = self::PHP_CONFIG
	): WikiConfigLookup {
		return new WikiConfigLookup(
			new ConfigSchema(),
			new StubWikiConfigSource( $pageData ),
			new HashConfig( $phpConfig ),
			$enabled,
			$logger ?? new TestLogger()
		);
	}

	public function testUsesThePhpValueWhenThereIsNoConfigPage(): void {
		$this->assertFalse( $this->newLookup( null )->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testUsesThePhpValueWhenTheKeyIsAbsentFromThePage(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testAValidPageValueWinsOverThePhpValue(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => true ] );

		$this->assertTrue( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testEachSettingIsResolvedIndependently(): void {
		$lookup = $this->newLookup( [ 'autoRenderMainSubject' => false ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'autoRenderMainSubject' ) );
	}

	public function testAnInvalidPageValueFallsBackToThePhpValue(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => 'yes' ] );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testAPhpValueOfTheWrongShapeComesDownToWhatItsTypeFallsBackTo(): void {
		$lookup = $this->newLookup( null, phpConfig: [ 'SWSDataLanguages' => 'de' ] + self::PHP_CONFIG );

		$this->assertSame( [], $lookup->getEffectiveValue( 'dataLanguages' ) );
	}

	public function testAnInvalidPageValueOverAPhpValueOfTheWrongShapeComesDownToWhatItsTypeFallsBackTo(): void {
		$lookup = $this->newLookup(
			[ 'subjectFirst' => 'yes' ],
			phpConfig: [ 'SWSSubjectFirst' => 'yes' ] + self::PHP_CONFIG
		);

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testThePhpValueIsTheLocalSettingsValueWhateverThePageSets(): void {
		$lookup = $this->newLookup(
			[ 'dataLanguages' => [ 'fr' ] ],
			phpConfig: [ 'SWSDataLanguages' => [ 'de', 'it' ] ] + self::PHP_CONFIG
		);

		$this->assertSame( [ 'de', 'it' ], $lookup->getPhpValue( 'dataLanguages' ) );
	}

	public function testAnInvalidPageValueLogsAWarning(): void {
		$logger = new TestLogger();

		$this->newLookup( [ 'subjectFirst' => 'yes' ], logger: $logger )
			->getEffectiveValue( 'subjectFirst' );

		$this->assertTrue( $logger->hasWarningRecords() );
	}

	public function testUnknownPageKeysAreToleratedOnRead(): void {
		$logger = new TestLogger();
		$lookup = $this->newLookup(
			[ 'subjectFirst' => true, 'someFutureKey' => 'whatever' ],
			logger: $logger
		);

		$this->assertTrue( $lookup->getEffectiveValue( 'subjectFirst' ) );
		$this->assertFalse( $logger->hasWarningRecords() );
	}

	public function testThePageIsIgnoredWhenInWikiConfigIsDisabled(): void {
		$lookup = $this->newLookup( [ 'subjectFirst' => true ], enabled: false );

		$this->assertFalse( $lookup->getEffectiveValue( 'subjectFirst' ) );
	}

	public function testTheConfigPageIsReadAtMostOncePerLookup(): void {
		$source = new StubWikiConfigSource( [ 'subjectFirst' => true ] );
		$lookup = new WikiConfigLookup(
			new ConfigSchema(),
			$source,
			new HashConfig( self::PHP_CONFIG ),
			true,
			new TestLogger()
		);

		$lookup->getEffectiveValue( 'subjectFirst' );
		$lookup->getEffectiveValue( 'autoRenderMainSubject' );

		$this->assertSame( 1, $source->readCount );
	}

}
