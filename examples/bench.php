<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

// Performance smoke harness (budgets gate from M5, informative until then).
// Usage: php examples/bench.php [--budget]

$budget = in_array('--budget', $argv ?? [], true);

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
    "bench M1: single-day median %.3f ms (M5 budget p50 < 2ms); year %.0f ms (M5 budget < 1000ms); peak %.1f MB (M5 budget < 16MB)\n",
    $singleMs,
    $yearMs,
    $peakMb,
);

if ($budget && ($singleMs > 50.0 || $yearMs > 30000.0)) {
    fwrite(STDERR, "bench budget exceeded\n");
    exit(1);
}
