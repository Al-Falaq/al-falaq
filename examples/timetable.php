<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Prayer\Enums\CalculationMethod;
use AlFalaq\AlFalaq\Prayer\Enums\Prayer;
use AlFalaq\AlFalaq\Prayer\Services\TimetableService;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(3.1390, 101.6869); // Kuala Lumpur
$zone = new DateTimeZone('Asia/Kuala_Lumpur');

$params = CalculationMethod::MUSLIM_WORLD_LEAGUE->defaultParameters();
$month = (new TimetableService())->monthly($coords, 2026, 1, $params);

foreach (array_slice($month, 0, 3) as $day => $schedule) {
    $local = $schedule->forZone($zone);
    echo sprintf(
        "2026-01-%02d fajr %s maghrib %s\n",
        $day + 1,
        $local->timeFor(Prayer::FAJR)->format('H:i'),
        $local->timeFor(Prayer::MAGHRIB)->format('H:i'),
    );
}
