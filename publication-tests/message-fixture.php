<?php
// Isolated host adapter: real message defaults, explicit wiki/content-language overrides.
if (!defined('NS_MEDIAWIKI')) { define('NS_MEDIAWIKI', 8); }
function swsFixtureMessage(string $key, mixed ...$args): object {
    return new class($key, $args) {
        private bool $content = false;
        public function __construct(private string $key, private array $args) {}
        public function inContentLanguage(): self { $this->content = true; return $this; }
        public function text(): string {
            $language = $this->content ? ($GLOBALS['swsTestContentLanguage'] ?? 'de') : ($GLOBALS['swsTestUiLanguage'] ?? 'de');
            $messages = json_decode(file_get_contents(dirname(__DIR__).'/i18n/'.$language.'.json'), true);
            $text = $GLOBALS['swsTestMessages'][$language][$this->key] ?? $messages[$this->key] ?? $this->key;
            foreach ($this->args as $i => $arg) { $text = str_replace('$'.($i+1), (string)$arg, $text); }
            return $text;
        }
    };
}
if (!function_exists('wfMessage')) {
    function wfMessage(string $key, mixed ...$args): object { return swsFixtureMessage($key, ...$args); }
}
