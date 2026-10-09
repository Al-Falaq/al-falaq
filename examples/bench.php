<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

// Performance budget harness (gates from M5, informative in M0-M4).
// Usage: php examples/bench.php [--budget]

$budget = in_array('--budget', $argv ?? [], true);

$start = hrtime(true);
// M0: single instantiation cost only (no astronomy yet).
$coords = AlFalaq\AlFalak\SharedKernel\ValueObjects\Coordinates::fromDegrees(3.1390, 101.6869);
$elapsedMs = (hrtime(true) - $start) / 1e6;

printf("bench M0: instantiate %.3f ms (budget: single-day p50 < 2ms from M5)\n", $elapsedMs);

if ($budget && $elapsedMs > 50.0) {
    fwrite(STDERR, "bench budget exceeded\n");
    exit(1);
}
