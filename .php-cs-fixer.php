<?php

declare(strict_types=1);

// Framework-agnostic style gate: PER coding style (the same ruleset
// laravel/pint's "per" preset wraps, without the framework branding).
// Usage: composer cs (check) / composer cs-fix (write).
$config = (new PhpCsFixer\Config())
    ->setRules(['@PER' => true])
    ->setFinder(
        PhpCsFixer\Finder::create()->in([
            __DIR__ . '/src',
            __DIR__ . '/tests',
            __DIR__ . '/examples',
            __DIR__ . '/tools',
        ]),
    );

return $config;
