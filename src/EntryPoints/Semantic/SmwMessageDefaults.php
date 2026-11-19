<?php

declare(strict_types=1);
namespace AEWI\SWS\EntryPoints\Semantic;

/** Persisted names belong to the wiki content language, never the reader's UI language. */
final class SmwMessageDefaults {
    public const URL_KEYS = [
        'url' => 'sws-smw-property-url', 'label' => 'sws-smw-property-label',
        'caption' => 'sws-smw-property-caption', 'captionPage' => 'sws-smw-property-caption-page',
        'field' => 'sws-smw-property-field', 'subject' => 'sws-smw-property-entity',
    ];
    public function read(array $overrides = []): array {
        $read = static function(string $key): string {
            return trim(wfMessage($key)->inContentLanguage()->text());
        };
        $names = [];
        foreach (self::URL_KEYS as $field => $key) { $names[$field] = $overrides['urlEntries'][$field] ?? $read($key); }
        return ['urlEntries' => $names, 'descriptionProperty' => $overrides['descriptionProperty'] ?? $read('sws-smw-property-description')];
    }
    /** Map only non-overridden names; the annotator records dependencies only when actually used. */
    public function dependencies(array $overrides, array $config): array {
        $dependencies = [];
        foreach (self::URL_KEYS as $field => $key) {
            if (!array_key_exists($field, $overrides['urlEntries'] ?? [])) {
                $dependencies[$config['urlEntries'][$field]][] = $key;
            }
        }
        if (!array_key_exists('descriptionProperty', $overrides)) {
            $dependencies[$config['descriptionProperty']][] = 'sws-smw-property-description';
        }
        return $dependencies;
    }
}
