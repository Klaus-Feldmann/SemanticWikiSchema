<?php

declare(strict_types=1);
namespace ProfessionalWiki\SWS\Application\Semantic;

use InvalidArgumentException;

/** Editable server mappings; schema field names remain the default property names. */
final class SmwMappingConfig {
    public static function resolve(mixed $overrides): array {
        $defaults = [
            'properties' => [],
            'urlEntries' => [
                'url' => 'Quellen-URL', 'label' => 'Quellentitel',
                'caption' => 'Quellenbezeichnung', 'captionPage' => 'Quellenbezeichnungsseite',
                'field' => 'WikiSchema-Feld', 'subject' => 'WikiSchema-Subjekt',
            ],
        ];
        if (!is_array($overrides) || array_diff(array_keys($overrides), array_keys($defaults))) {
            throw new InvalidArgumentException('WikiSchemaSmwMapping: expected properties and/or urlEntries arrays.');
        }
        foreach ($overrides as $key => $value) {
            if (!is_array($value)) {
                throw new InvalidArgumentException('WikiSchemaSmwMapping: '.$key.' must be an array.');
            }
        }
        $config = $defaults;
        $config['properties'] = $overrides['properties'] ?? [];
        foreach ($config['properties'] as $schema => $fields) {
            if (!is_string($schema) || trim($schema) === '' || !is_array($fields)) {
                throw new InvalidArgumentException('WikiSchemaSmwMapping: expected schema-to-field mappings.');
            }
            foreach ($fields as $field => $name) {
                if (!is_string($field) || trim($field) === '') {
                    throw new InvalidArgumentException('WikiSchemaSmwMapping: invalid field name.');
                }
                self::validateName($name);
            }
        }
        $urls = $overrides['urlEntries'] ?? [];
        if (array_diff(array_keys($urls), array_keys($defaults['urlEntries']))) {
            throw new InvalidArgumentException('WikiSchemaSmwMapping: unknown URL entry key.');
        }
        foreach ($urls as $key => $name) {
            self::validateName($name);
            $config['urlEntries'][$key] = $name;
        }
        return $config;
    }

    private static function validateName(mixed $name): void {
        if (!is_string($name) || trim($name) === '' ||
            in_array(trim($name), ['template', '@json'], true) ||
            str_starts_with(trim($name), '+') || str_contains($name, '::') ||
            preg_match('/[=|\[\]{}<>\r\n]/u', $name)) {
            throw new InvalidArgumentException('WikiSchemaSmwMapping: invalid SMW property name.');
        }
    }
}
