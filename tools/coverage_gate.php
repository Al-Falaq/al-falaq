<?php

declare(strict_types=1);

// Coverage gate without test-runner plugins: runs PHPUnit once with coverage,
// parses the "Lines:" summary, and enforces --min.
//
// Ratchet policy: the floor starts at the M0 baseline
// (20) and is raised per milestone as contexts land; M5 restores 95.
// Without a coverage driver (pcov/xdebug) the gate passes on green tests
// with a notice — CI always provides pcov, so the floor is enforced there.
// Usage: php tools/coverage_gate.php [--min=20]
$root = dirname(__DIR__);
chdir($root);

$min = 20.0;

foreach ($argv ?? [] as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--min=')) {
        $raw = substr($arg, strlen('--min='));

        if (! is_numeric($raw)) {
            fwrite(STDERR, "coverage_gate: --min must be numeric 0-100, got '{$raw}'.\n");
            exit(1);
        }

        $min = (float) $raw;

        if ($min < 0.0 || $min > 100.0) {
            fwrite(STDERR, "coverage_gate: --min must be within 0-100, got {$min}.\n");
            exit(1);
        }
    }
}

$phpunit = $root . '/vendor/bin/phpunit';

if (! is_file($phpunit)) {
    fwrite(STDERR, "coverage_gate: vendor/bin/phpunit not found; run composer install first.\n");
    exit(1);
}

// Single run: with a driver this yields coverage; without one it still runs
// the suite (PHPUnit prints "No code coverage driver available").
exec(PHP_BINARY . ' ' . escapeshellarg($phpunit) . ' --coverage-text --colors=never 2>&1', $lines, $exit);

$output = implode("\n", $lines);

if (str_contains($output, 'No code coverage driver available')) {
    echo $output, "\n";

    if ($exit !== 0) {
        fwrite(STDERR, "coverage_gate: test run failed.\n");
        exit($exit);
    }

    // Machine-readable bypass marker: driverless runs pass on green tests
    // with the threshold explicitly unchecked (CI always provides pcov).
    echo "coverage_gate: status=no-driver threshold-unchecked tests-green\n";
    fwrite(STDERR, "coverage_gate: no coverage driver (pcov/xdebug); threshold unchecked, tests green.\n");
    exit(0);
}

echo $output, "\n";

if ($exit !== 0 && ! str_contains($output, 'Lines:')) {
    fwrite(STDERR, "coverage_gate: test run failed before coverage could be measured.\n");
    exit($exit);
}

if (! preg_match('/^\s*Lines:\s+([0-9.]+)\s*%/m', $output, $matches)
    && ! preg_match('/Lines\s*:\s*([0-9]+(?:\.[0-9]+)?)\s*%/', $output, $matches)
) {
    fwrite(STDERR, "coverage_gate: could not parse total coverage.\n");
    exit(1);
}

$actual = (float) $matches[1];
printf("coverage_gate: total %.1f %% vs min %.1f %%\n", $actual, $min);

if ($exit !== 0) {
    exit($exit);
}

exit($actual >= $min ? 0 : 1);
