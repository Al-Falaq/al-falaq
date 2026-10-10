<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\PrayerTimesCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\CivilDate;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(3.1390, 101.6869); // Kuala Lumpur
$zone = new DateTimeZone('Asia/Kuala_Lumpur');
$date = CivilDate::fromDateTime(new DateTimeImmutable('now', $zone));

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
$schedule = PrayerTimesCalculator::defaultFor($params)->calculate($coords, $date, $params);

echo sprintf("Fajr today (%s): %s\n", $date->isoformat(), $schedule->forZone($zone)->timeFor(Prayer::FAJR)->format('H:i'));
