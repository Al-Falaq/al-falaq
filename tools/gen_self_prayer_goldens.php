<?php

declare(strict_types=1);

/**
 * Generate self-established M2 prayer goldens for TEHRAN + MOROCCO.
 *
 * No Python-pin vectors exist for these presets (see the M2 design spec), so
 * goldens are produced by the shipped implementation from the cited angles:
 * Tehran Fajr 17.7 / Isha 14 / Maghrib-angle 4.5 (Institute of Geophysics,
 * University of Tehran); Morocco Fajr 19 / Isha 17 (Ministry of Habous).
 * Grid mirrors tools/export_goldens.py. Regenerate and diff-review with:
 * php tools/gen_self_prayer_goldens.php
 */

require __DIR__ . '/../vendor/autoload.php';

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\Exceptions\AstronomicalException;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$markers = array_map(static fn(Prayer $p): string => $p->value, Prayer::cases());

$locations = [
    [3.1390, 101.6869],
    [40.7128, -74.0060],
    [51.5074, -0.1278],
    [59.9139, 10.7522],
    [69.6496, 18.9553],
    [-33.8688, 151.2093],
    [21.4225, 39.8262],
    [-6.2000, 106.8500],
];

$dates = ['2025-01-15', '2025-03-20', '2025-06-21', '2025-09-22', '2025-12-15', '2025-03-02'];

$methods = [CalculationMethod::TEHRAN, CalculationMethod::MOROCCO];

foreach ($methods as $method) {
    $path = __DIR__ . '/../tests/Goldens/prayer_' . $method->value . '.csv';
    $tmpPath = $path . '.tmp';
    $handle = fopen($tmpPath, 'w');

    if ($handle === false) {
        throw new RuntimeException('Cannot open ' . $tmpPath);
    }

    fputcsv($handle, array_merge(['method', 'date', 'latitude', 'longitude'], $markers, ['source']));

    foreach ($dates as $datestr) {
        [$y, $m, $d] = array_map('intval', explode('-', $datestr));

        foreach ($locations as [$lat, $lon]) {
            $row = [$method->value, $datestr, sprintf('%.4f', $lat), sprintf('%.4f', $lon)];

            try {
                $schedule = PrayerTimesCalculator::default()->calculate(
                    Coordinates::fromDegrees($lat, $lon),
                    CivilDate::fromYmd($y, $m, $d),
                    $method->defaultParameters(),
                );

                foreach ($markers as $marker) {
                    $row[] = $schedule->timeFor(Prayer::from($marker))->format('Y-m-d\TH:i:s+00:00');
                }
            } catch (AstronomicalException $e) {
                // Unresolvable by construction (e.g. Tehran AngleMaghrib 4.5°
                // during polar day: the sun never dips that low and maghrib
                // has no high-latitude fallback). M1 precedent (adee976):
                // empty the marker cells with a per-row reason; the golden
                // test skips empty fields.
                foreach ($markers as $marker) {
                    $row[] = '';
                }

                fwrite(STDERR, sprintf(
                    "empty %s %s %.4f,%.4f: %s\n",
                    $method->value,
                    $datestr,
                    $lat,
                    $lon,
                    $e->getMessage(),
                ));
            }

            $row[] = 'self:m2-2026-10-10;tehran:Geophysics-UT-17.7/14/maghrib-4.5;morocco:Habous-19/17';
            fputcsv($handle, $row);
        }
    }

    fclose($handle);

    if (! rename($tmpPath, $path)) {
        throw new RuntimeException('Cannot replace ' . $path);
    }

    echo 'wrote ' . $path . PHP_EOL;
}
