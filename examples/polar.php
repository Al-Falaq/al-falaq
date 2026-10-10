<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\PolarCircleRule;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Exceptions\UndefinedScheduleException;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$tromso = Coordinates::fromDegrees(69.6496, 18.9553);
$zone = new DateTimeZone('Europe/Oslo');

// Midnight sun (2026-06-21): no local sunrise — NEAREST_LATITUDE (the
// default polar rule) bisects toward the equator for a solvable schedule.
$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
$summer = PrayerTimesCalculator::defaultFor($params)->calculate($tromso, CivilDate::fromYmd(2026, 6, 21), $params);
echo sprintf("Tromso 2026-06-21 fajr %s\n", $summer->forZone($zone)->timeFor(Prayer::FAJR)->format('H:i'));

// Polar night (2026-12-21) with NONE: no fallback — the re-probe throws.
$none = $params->withPolarCircleRule(PolarCircleRule::NONE);

try {
    PrayerTimesCalculator::defaultFor($none)->calculate($tromso, CivilDate::fromYmd(2026, 12, 21), $none);
    echo "unexpected: polar night resolved\n";
} catch (UndefinedScheduleException $e) {
    echo sprintf("polar night (NONE): %s\n", $e->getMessage());
}
