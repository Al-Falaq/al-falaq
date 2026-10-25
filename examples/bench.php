<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

// Performance tripwire harness.
//
// The ENFORCED gates are the contract: a breach fails the build (--budget).
// The PRD figures are hardware- AND load-dependent headroom references and
// are printed on their own line, explicitly NOT gated. Measured on one
// machine, the same script reports a single-day median of 2.4ms, 6-7.6ms
// and 39.5ms under different load — a 16x spread — so an absolute gate
// anywhere near the PRD 2ms figure would be flaky rather than meaningful.
// The tripwires stay loose on purpose: the single-day one sits ~7-20x above
// the measured median on a quiet machine but only ~1.3x above it under heavy
// load, so they catch a gross regression rather than measuring speed.
//
// Usage: php examples/bench.php [--budget]

$budget = in_array('--budget', $argv ?? [], true);
$singleTripwireMs = 50.0;   // enforced tripwire (unchanged)
$yearTripwireMs = 30000.0;  // enforced tripwire (unchanged)

$coords = Coordinates::fromDegrees(3.1390, 101.6869);
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
$calc = PrayerTimesCalculator::default();

// Single-day: median of 21 runs (p50-ish).
$singles = [];

for ($i = 0; $i < 21; $i++) {
    $start = hrtime(true);
    $calc->calculate($coords, CivilDate::fromYmd(2025, 1, 15), $params);
    $singles[] = (hrtime(true) - $start) / 1e6;
}

sort($singles);
$singleMs = $singles[10];

// Full year: 12 monthly timetables.
$start = hrtime(true);
$timetable = new TimetableService();

for ($m = 1; $m <= 12; $m++) {
    $timetable->monthly($coords, 2025, $m, $params);
}

$yearMs = (hrtime(true) - $start) / 1e6;
$peakMb = memory_get_peak_usage(true) / 1024 / 1024;

printf(
    "bench: single-day median %.3f ms (tripwire %.0f ms); year %.0f ms (tripwire %.0f ms); peak %.1f MB (not gated)\n",
    $singleMs,
    $singleTripwireMs,
    $yearMs,
    $yearTripwireMs,
    $peakMb,
);
printf(
    "bench: PRD reference (NOT gated, hardware- and load-dependent): single-day p50 < 2ms; year < 1000ms; peak < 16MB\n",
);

if ($budget && ($singleMs > $singleTripwireMs || $yearMs > $yearTripwireMs)) {
    fwrite(STDERR, "bench budget exceeded\n");
    exit(1);
}
