<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $testPrefix = 'ProfessionalWiki\\SWS\\Tests\\';
    if (str_starts_with($class, $testPrefix)) {
        $file = __DIR__ . '/../phpunit/' . str_replace('\\', '/', substr($class, strlen($testPrefix))) . '.php';
        if (is_file($file)) { require $file; }
        return;
    }
    $prefix = 'ProfessionalWiki\\SWS\\';
    if (str_starts_with($class, $prefix)) {
        $file = __DIR__ . '/../../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});
