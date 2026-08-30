<?php

declare(strict_types=1);

(static function (): void {
    foreach ([
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'sqlite',
        'DB_DATABASE' => ':memory:',
        'DB_URL' => '',
    ] as $name => $value) {
        if (! putenv("{$name}={$value}")) {
            throw new RuntimeException("Unable to set {$name} for PHPUnit.");
        }

        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;

        if (getenv($name) !== $value || $_ENV[$name] !== $value || $_SERVER[$name] !== $value) {
            throw new RuntimeException("Unable to enforce {$name} for PHPUnit.");
        }
    }
})();

require dirname(__DIR__).'/vendor/autoload.php';
