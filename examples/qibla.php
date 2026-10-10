<?php

declare(strict_types=1);

require __DIR__ . "/../vendor/autoload.php";

use AlFalaq\AlFalaq\Qibla\Services\QiblaCalculator;
use AlFalaq\AlFalaq\SharedKernel\ValueObjects\Coordinates;

$coords = Coordinates::fromDegrees(3.1390, 101.6869);

try {
    $result = QiblaCalculator::default()->calculate($coords);
    printf("Qibla: %.4f° (%.2f km)\n", $result->direction, $result->distanceKm);
} catch (LogicException $e) {
    echo 'M0: ' . $e->getMessage() . PHP_EOL;
}
