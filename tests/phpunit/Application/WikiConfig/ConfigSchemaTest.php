<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Tests\Application\WikiConfig;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\SWS\Application\WikiConfig\ConfigSchema;

/**
 * @covers \ProfessionalWiki\SWS\Application\WikiConfig\ConfigSchema
 * @covers \ProfessionalWiki\SWS\Application\WikiConfig\ConfigSetting
 */
class ConfigSchemaTest extends TestCase {

	public function testExposesTheSubjectFirstSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'subjectFirst' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'SWSSubjectFirst', $setting->settingName );
	}

	public function testExposesTheAutoRenderSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'autoRenderMainSubject' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'SWSAutoRenderMainSubject', $setting->settingName );
	}

	public function testExposesTheDataLanguagesSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'dataLanguages' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'SWSDataLanguages', $setting->settingName );
	}

	public function testExposesTheEnforceValidationSetting(): void {
		$setting = ( new ConfigSchema() )->getSetting( 'enforceValidation' );

		$this->assertNotNull( $setting );
		$this->assertSame( 'SWSEnforceValidation', $setting->settingName );
	}

	public function testExposesExactlyTheAllowlistedSettings(): void {
		$keys = array_map(
			static fn ( $setting ): string => $setting->pageKey,
			( new ConfigSchema() )->getSettings()
		);

		$this->assertEqualsCanonicalizing( [ 'subjectFirst', 'autoRenderMainSubject', 'dataLanguages', 'enforceValidation' ], $keys );
	}

	public function testUnknownKeyHasNoSetting(): void {
		$this->assertNull( ( new ConfigSchema() )->getSetting( 'SWSSparqlStores' ) );
	}

}
