<?php

declare(strict_types=1);

/**
 * PHPUnit bootstrap for the test suite.
 *
 * Why this exists: PHPUnit's <env force="true"> updates putenv() and $_ENV,
 * but NOT $_SERVER. On machines where APP_ENV / DB_CONNECTION / DB_DATABASE
 * are set as OS-level environment variables (common on Windows dev boxes),
 * $_SERVER wins inside Laravel's env repository, so tests would silently
 * boot with the real environment (e.g. production + PostgreSQL) instead of
 * the testing config. This bootstrap mirrors every <env> entry from
 * phpunit.xml into all three stores before the application boots.
 */

require __DIR__ . '/../vendor/autoload.php';

$phpunitXml = simplexml_load_file(__DIR__ . '/../phpunit.xml');

if ($phpunitXml === false || !isset($phpunitXml->php->env)) {
    return;
}

foreach ($phpunitXml->php->env as $env) {
    $name = (string) $env['name'];
    $value = (string) $env['value'];
    $force = strtolower((string) ($env['force'] ?? 'false')) === 'true';

    if ($name === '' || (!$force && getenv($name) !== false)) {
        continue;
    }

    putenv("{$name}={$value}");
    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
}
