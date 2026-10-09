<?php

declare(strict_types=1);

// Coverage gate without test-runner plugins: runs PHPUnit with a coverage
// driver, parses the "Lines:" summary, and enforces --min.
//
// Ratchet policy (Technical-PRD.md §15): the floor starts at the M0 baseline
// (20) and is raised per milestone as contexts land; M5 restores 95.
// Without a coverage driver (pcov/xdebug) the gate still runs the suite and
// passes on green tests with a notice — CI always provides pcov, so the
// floor is enforced there.
// Usage: php tools/coverage_gate.php [--min=20]
$root = dirname(__DIR__);
chdir($root);

$min = 20.0;

foreach ($argv ?? [] as $arg) {
    if (is_string($arg) && str_starts_with($arg, '--min=')) {
        $min = (float) substr($arg, strlen('--min='));
    }
}

$phpunit = $root . '/vendor/bin/phpunit';

if (! is_file($phpunit)) {
    fwrite(STDERR, "coverage_gate: vendor/bin/phpunit not found; run composer install first.\n");
    exit(1);
}

exec(PHP_BINARY . ' ' . escapeshellarg($phpunit) . ' --no-coverage --colors=never 2>&1', $unitLines, $unitExit);

if ($unitExit !== 0) {
    echo implode("\n", $unitLines), "\n";
    fwrite(STDERR, "coverage_gate: test run failed.\n");
    exit($unitExit);
}

exec(PHP_BINARY . ' ' . escapeshellarg($phpunit) . ' --coverage-text --colors=never 2>&1', $lines, $exit);

$output = implode("\n", $lines);

if (str_contains($output, 'No code coverage driver available')) {
    echo $output, "\n";
    fwrite(STDERR, "coverage_gate: no coverage driver (pcov/xdebug); threshold unchecked, tests green.\n");
    exit(0);
}

echo $output, "\n";

if ($exit !== 0 && ! str_contains($output, 'Lines:')) {
    fwrite(STDERR, "coverage_gate: test run failed before coverage could be measured.\n");
    exit($exit);
}

if (! preg_match('/^\s*Lines:\s+([0-9.]+)\s*%/m', $output, $matches)) {
    fwrite(STDERR, "coverage_gate: could not parse total coverage.\n");
    exit(1);
}

$actual = (float) $matches[1];
printf("coverage_gate: total %.1f %% vs min %.1f %%\n", $actual, $min);

if ($exit !== 0) {
    exit($exit);
}

exit($actual >= $min ? 0 : 1);
