<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\SWS\Application\WikiConfig;

/**
 * The allowlist of settings the on-wiki configuration page (MediaWiki:SWS) may set. The allowlist is
 * the security boundary: only these settings are readable from and writable on the page. Everything else
 * stays exclusive to LocalSettings.php — infrastructure and secrets (SWSSparqlStores), settings too
 * consequential for a wiki page (SWSRdfBaseUri re-mints every IRI), and dev-only toggles
 * (SWSEnableDevelopmentUI). New settings are vetted and added one by one.
 */
class ConfigSchema {

	/**
	 * @var ConfigSetting[]
	 */
	private array $settings;

	public function __construct() {
		$this->settings = [
			new ConfigSetting(
				pageKey: 'subjectFirst',
				settingName: 'SWSSubjectFirst',
				valueType: ConfigValueType::Boolean,
			),
			new ConfigSetting(
				pageKey: 'dataLanguages',
				settingName: 'SWSDataLanguages',
				valueType: ConfigValueType::LanguageList,
			),
			new ConfigSetting(
				pageKey: 'enforceValidation',
				settingName: 'SWSEnforceValidation',
				valueType: ConfigValueType::Boolean,
			),
			new ConfigSetting(
				pageKey: 'autoRenderMainSubject',
				settingName: 'SWSAutoRenderMainSubject',
				valueType: ConfigValueType::Boolean,
			),
		];
	}

	/**
	 * @return ConfigSetting[]
	 */
	public function getSettings(): array {
		return $this->settings;
	}

	public function getSetting( string $pageKey ): ?ConfigSetting {
		foreach ( $this->settings as $setting ) {
			if ( $setting->pageKey === $pageKey ) {
				return $setting;
			}
		}

		return null;
	}

}
